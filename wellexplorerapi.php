<?php

// Allow Cross-Origin Resource Sharing
// header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Origin: http://wellexplorer.org");

// Create connection to database
$con=mysqli_connect("localhost","fertil38_fertil3","Cheese88!","fertil38_wellexplorer");

// Check connection
if (mysqli_connect_errno())
{
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

// var_dump($_POST);
if (((isset($_POST['latitude']) && isset($_POST['longitude'])) || isset($_POST['order_by'])) && $_POST['response_type'] == "well") {
    // Gets data from database with SQL statement
    $latitude = $con->real_escape_string($_POST['latitude']);
    $longitude = $con->real_escape_string($_POST['longitude']);
    $well_name_post = $con->real_escape_string($_POST['well_name']);
    $ingredient_name_post = $con->real_escape_string($_POST['ingredient_name']);
    $gene_name_post = $con->real_escape_string($_POST['gene_name']);
    $toxic_post = $con->real_escape_string($_POST['toxic']);
    $pathways_post = $con->real_escape_string($_POST['pathways']);
    $order_by_post = $con->real_escape_string($_POST['order_by']);
    $num_wells_post = $con->real_escape_string($_POST['num_wells']);
    $county_flag_post = $con->real_escape_string($_POST['county_flag_filter']);

    $well_name_query = "1";
    if ($well_name_post != "") {
        $well_name_query = "(well_name LIKE '%".$well_name_post."%' OR operator_name LIKE '%".$well_name_post."%')";
    }

    $ingredient_name_query = "1";
    if ($ingredient_name_post != "") {
        $ingredient_name_query = "((well_id IN (SELECT well_id FROM well_ingredients WHERE cas_number LIKE '%".$ingredient_name_post."%')) OR (well_id IN (SELECT well_id FROM well_ingredients WHERE cas_number IN (SELECT cas_number FROM ingredients WHERE ingredient_name LIKE '%".$ingredient_name_post."%'))))";
    }

    $gene_name_query = "1";
    if ($gene_name_post != "") {
        $gene_name_query = "well_id IN (SELECT well_id FROM well_ingredients WHERE cas_number IN (SELECT cas_number FROM ingredients WHERE t3db_id IN (SELECT t3db_id FROM ingredient_path_properties WHERE uniprot_id IN (SELECT uniprot_id FROM path_properties WHERE gene_name LIKE '%".$gene_name_post."%'))))";
    }

    $toxic_query = "1";
    if ($toxic_post == "Y" || ((!isset($_POST['latitude']) || !isset($_POST['longitude'])) && $order_by_query == "toxicity")) {
        $toxic_query = "toxicity > 0";
    }

    $pathways_query = "1";
    if (preg_match('/[HEThet][HEThet]?[HEThet]?/', $pathways_post)) {
        $pathways_query = "(pathways LIKE '%".$pathways_post."%')";
    }

    $order_by_query = "well_name";
    if ($order_by_post == "well_depth") {
        $order_by_query = "well_depth DESC";
    } else if ($order_by_post == "toxicity") {
        $order_by_query = "toxicity";
    }

    $num_wells_query = 100;
    if ($num_wells_post != "") {
        $num_wells_query = $num_wells_post;
    }

    $county_flag_query = "1";
    if ($county_flag_post == "Y") {
        $county_flag_query = "county_flag = 'N'";
    }

    $sql_query = "";
    if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
        $sql_query = "SELECT well_id, well_name, latitude, longitude, 7919.2*ATAN2(SQRT(SIN((latitude-".$latitude.")*PI()/360)*SIN((latitude-".$latitude.")*PI()/360)+COS(latitude*PI()/180)*COS(".$latitude."*PI()/180)*SIN((longitude-".$longitude.")*PI()/360)*SIN((longitude-".$longitude.")*PI()/360)), SQRT(1-(SIN((latitude-".$latitude.")*PI()/360)*SIN((latitude-".$latitude.")*PI()/360)+COS(latitude*PI()/180)*COS(".$latitude."*PI()/180)*SIN((longitude-".$longitude.")*PI()/360)*SIN((longitude-".$longitude.")*PI()/360)))) distance, state, county, toxicity, pathways, no_ingredients FROM wells WHERE ".$county_flag_query." AND ".$well_name_query." AND ".$ingredient_name_query." AND ".$gene_name_query." AND ".$toxic_query." AND ".$pathways_query." ORDER BY distance LIMIT ".$num_wells_query.";";
    } else {
        $sql_query = "SELECT well_id, well_name, latitude, longitude, 0 distance, state, county, toxicity, pathways, no_ingredients FROM wells WHERE ".$county_flag_query." AND ".$well_name_query." AND ".$ingredient_name_query." AND ".$gene_name_query." AND ".$toxic_query." AND ".$pathways_query." ORDER BY ".$order_by_query." LIMIT ".$num_wells_query.";";
    }

    $stmt = $con->prepare($sql_query);
    $stmt->execute();
    $result = $stmt->get_result();
    $json_array = array();

    // Prepares all the results to be encoded in a JSON
    while ($row = $result->fetch_assoc()) {
        $well_result = array('well_id' => $row['well_id'], 'well_name' => $row['well_name'], 'longitude' => $row['longitude'], 'latitude' => $row['latitude'], 'distance' => $row['distance'], 'state' => $row['state'], 'county' => $row['county'], 'toxicity' => $row['toxicity'], 'pathways' => $row['pathways'], 'no_ingredients' => $row['no_ingredients']);
        array_push($json_array, $well_result);
    }

    $response = json_encode($json_array);

} else if (isset($_POST['well_id']) && $_POST['response_type'] == "well") {

    // Gets data from database with SQL statement
   $well_id = $con->real_escape_string($_POST['well_id']);
   $stmt = $con->prepare("SELECT well_id, well_name, operator_name, latitude, longitude, well_depth, total_base_water_volume, state, county FROM wells WHERE well_id = ?;");
   $stmt->bind_param("d", $well_id);
   $stmt->execute();
   $result = $stmt->get_result();

   $row = $result->fetch_assoc();
   $response = json_encode(array('well_id' => $row['well_id'], 'well_name' => $row['well_name'], 'operator_name' => $row['operator_name'], 'latitude' => $row['latitude'], 'longitude' => $row['longitude'], 'depth' => $row['well_depth'], 'volume' => $row['total_base_water_volume'], 'state' => $row['state'], 'county' => $row['county']));

} else if (isset($_POST['well_id']) && $_POST['response_type'] == "ingredients") {

    // Gets data from database with SQL statement
   $well_id = $con->real_escape_string($_POST['well_id']);
   $stmt = $con->prepare("SELECT ingredient_name, well_ingredients.cas_number, supplier, purpose, pathway, toxicity, eafus, t3db_id, protein_targets FROM well_ingredients INNER JOIN ingredients ON well_ingredients.cas_number = ingredients.cas_number WHERE well_id = ? ORDER BY ingredients.order_id;");
   $stmt->bind_param("d", $well_id);
   $stmt->execute();
   $result = $stmt->get_result();
   $json_array = array();

   // Prepares all the results to be encoded in a JSON
   while ($row = $result->fetch_assoc()) {
       $ingredients_result = array('ingredient_name' => $row['ingredient_name'], 'cas' => $row['cas_number'], 'supplier' => $row['supplier'], 'purpose' => $row['purpose'], 'pathway' => $row['pathway'], 'toxicity' => $row['toxicity'], 'eafus' => $row['eafus'], 't3db' => $row['t3db_id'], 'protein_targets' => $row['protein_targets']);
       array_push($json_array, $ingredients_result);
   }

   $response = json_encode($json_array);

} else if ($_POST['response_type'] == "ingredients") {

    $ingredient_name_post = $con->real_escape_string($_POST['ingredient_name']);
    $cas_number_post = $con->real_escape_string($_POST['cas_number']);
    $gene_name_post = $con->real_escape_string($_POST['gene_name']);
    $toxic_post = $con->real_escape_string($_POST['toxic']);
    $eafus_post = $con->real_escape_string($_POST['food_additive']);
    $pathways_post = $con->real_escape_string($_POST['pathways']);
    $order_by = $con->real_escape_string($_POST['order_by']);

    $ingredient_name_query = "1";
    if ($ingredient_name_post != "") {
        $ingredient_name_query = "ingredient_name LIKE '%".$ingredient_name_post."%'";
    }

    $cas_number_query = "1";
    if ($cas_number_post != "") {
        $cas_number_query = "cas_number LIKE '%".$cas_number_post."%'";
    }

    $gene_name_query = "1";
    if ($gene_name_post != "") {
        $gene_name_query = " t3db_id IN (SELECT t3db_id FROM ingredient_path_properties WHERE uniprot_id IN (SELECT uniprot_id FROM path_properties WHERE gene_name LIKE '%".$gene_name_post."%'))";
    }

    $toxic_query = "1";
    if ($toxic_post == "Y") {
        $toxic_query = "toxicity > 0";
    }

    $eafus_query = "1";
    if ($eafus_post == "Y") {
        $eafus_query = "eafus = 'Y'";
    }

    $pathways_query = "1";
    if (preg_match('/[HEThet][HEThet]?[HEThet]?/', $pathways_post)) {
        $pathways_post_array = str_split($pathways_post);
        $pathways_query = "pathway LIKE '%".$pathways_post_array[0]."%'";
        for ($x = 1; $x < count($pathways_post_array); $x++) {
            $pathways_query = $pathways_query."AND pathway LIKE '%".$pathways_post_array[$x]."%'";
        }
    }

    if ($order_by != "cas_number" && $order_by != "toxicity") {
        $order_by = "ingredient_name";
    }

    // Gets data from database with SQL statement
    $sql_query = "SELECT ingredient_name, cas_number, pathway, toxicity, eafus, t3db_id, protein_targets FROM ingredients WHERE ".$ingredient_name_query." AND ".$cas_number_query." AND ".$gene_name_query." AND ".$toxic_query." AND ".$eafus_query." AND ".$pathways_query." ORDER BY ".$order_by.";";
    if ($order_by == "toxicity") {
        $sql_query = "SELECT ingredient_name, cas_number, pathway, toxicity, eafus, t3db_id, protein_targets FROM ((SELECT * FROM (SELECT * FROM ingredients WHERE toxicity > 0 ORDER BY toxicity) temp_ingredients) UNION (SELECT * FROM ingredients WHERE toxicity = 0)) total_temp_ingredients WHERE ".$ingredient_name_query." AND ".$cas_number_query." AND ".$gene_name_query." AND ".$toxic_query." AND ".$eafus_query." AND ".$pathways_query.";";
    }
    $stmt = $con->prepare($sql_query);
    $stmt->execute();
    $result = $stmt->get_result();
    $json_array = array();

    // Prepares all the results to be encoded in JSON
    while ($row = $result->fetch_assoc()) {
        $ingredients_result = array('ingredient_name' => $row['ingredient_name'], 'cas' => $row['cas_number'], 'pathway' => $row['pathway'], 'toxicity' => $row['toxicity'], 'eafus' => $row['eafus'], 't3db' => $row['t3db_id'],'protein_targets' => $row['protein_targets']);
        array_push($json_array, $ingredients_result);
    }

    $response = json_encode($json_array);

} else if (isset($_POST['t3db_id']) && $_POST['response_type'] == "protein_targets") {

    // Gets data from database with SQL statement
    $t3db_id = $con->real_escape_string($_POST['t3db_id']);
    $stmt = $con->prepare("SELECT path_properties.uniprot_id, mechanism, protein_name, gene_name, protein_function, pathway_affected FROM ingredient_path_properties INNER JOIN path_properties ON ingredient_path_properties.uniprot_id = path_properties.uniprot_id WHERE t3db_id = ?;");
    $stmt->bind_param("s", $t3db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $json_array = array();
 
    // Prepares all the results to be encoded in a JSON
    while ($row = $result->fetch_assoc()) {
        $ingredients_result = array('uniprot' => $row['uniprot_id'], 'protein_name' => $row['protein_name'], 'gene_name' => $row['gene_name'], 'pathway' => $row['pathway_affected'], 'mechanism' => $row['mechanism'], 'protein_function' => $row['protein_function']);
        array_push($json_array, $ingredients_result);
    }
 
    $response = json_encode($json_array);
 

}



// encodes array with results from database
echo $response;
mysqli_close($con);

?>

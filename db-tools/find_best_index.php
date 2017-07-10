<?php
$server='localhost'; 
$user='root';
$password='secret'; 
$database='db_test';

//$server='zigabyte.net'; 
//$user='radu';
//$password='radu'; 
//$database='db_ctcdev';

$table = $argv[1];
$query = $argv[2];
$columns = split(",", $argv[3]);

// show the parameters
print("table name: " . $table . "\n");
print("query: " . $query . "\n");
print("columns: " . $argv[3] . "\n");
print("\n");

// connect to server and select database
// flags: 65536, 131072, and 199608
$link_proc = mysqli_connect($server, $user, $password, false, 199608)
        or die("Could not connect: " . mysqli_error());
$link = mysqli_connect($server, $user, $password, true)
        or die("Could not connect the second time: " . mysqli_error());

mysqli_select_db($database) or die("Could not select database : " . mysqli_error());

// check the table name
$check_query = "select count(*) from " . $table;
mysqli_query($check_query) or die("Invalid table name: " . $table);

// check columns names
foreach($columns as $col) {
	$check_query = "select " . $col . " from " . $table;
	mysqli_query($check_query) or die("Invalid column name: " . $col);
}

// check the query
$result = mysqli_query($query, $link_proc) or die("Invalid query: " . mysqli_error($link_proc));
if($result) {
	mysqli_free_result($result);
}

// delete indexes on table
drop_indexes($table);

// create all possible columns combinations
$combinations = columns_combinations($columns);
//print_r($combinations);

// start clocking the queries
$min_time = 100000;
$best_combination = "";

foreach($combinations as $combination) {
	$imploded_combination = implode(",", $combination);
	drop_indexes($table);
	create_index($table, "idx_test", $imploded_combination);
	
	$current_time = clock_query($query, $link_proc);
	
	//print("\n\nCombination: " . $imploded_combination . "\nTime: " . $current_time);
	print("\n" . $imploded_combination . " " . $current_time);
	if($current_time < $min_time) {
		$min_time = $current_time;
		$best_combination = $imploded_combination;
	}
}

drop_indexes($table);

// print the best result
print("\n\nThe best combination: " . $best_combination . "\nThe best time: " . $min_time);
print("\nCREATE INDEX idx_text ON " . $table . " (" . $best_combination . ")");

// utility functions
/* Drops all indexes on a table.
   Receives as argument the table name.
*/   
function drop_indexes($table) {
	$indexes = array();
	$primary_key = array();
	// get the list of indexes
	$result = mysqli_query("show index from " . $table) or die("Error during index enumeration: " . mysqli_error());
	while($row = mysqli_fetch_assoc($result)) {
		if($row["Key_name"] != "PRIMARY") {
			$indexes[] = $row["Key_name"];
		}
		else {
			$primary_key[] = $row["Key_name"];
		}
	}
	mysqli_free_result($result);
	// if an index has more columns, its name appears more times in array. Keep one appearance of each index name in array.
	$indexes = array_unique($indexes);
	// delete indexes
	foreach($indexes as $index) {
		mysqli_query("drop index " . $index . " on " . $table) or die("Error during index deletion (" . $index . "): " . mysqli_error());
	}
	if(sizeof($primary_key) > 0) {
		mysqli_query("alter table " . $table . " drop primary key") or die("Error during index deletion (" . $index . "): " . mysqli_error());
	}
}

/* Creates an index on a table.
   Arguments:
      $table - the name of the table
      $index_name - the name of the index to be created
      $columns_combination - the list of columns in the index
   Obs: all columns in the index will be sorted ascending
*/
function create_index($table, $index_name, $columns_combination) {
	$query = "create index " . $index_name . " on " . $table . " (" . $columns_combination . ")";
	mysqli_query($query) or die("Error during creating index (" . $columns_combination . "): " . mysqli_error());
}

/* Finds all combinations of elements in the array.
   Argument:
      $columns - an array
   Returns:
      an array containing each possible combinations of elements, in each possible order
   Obs: 
*/
function columns_combinations($columns) {
	$combinations = array();
	
	$columns_count = sizeof($columns);
	
	// build an union query that returns all the elements in $columns
	$union_query = "select '{$columns[0]}'";
	for($i = 1; $i < $columns_count; ++$i) {
		$union_query .= " union select '{$columns[$i]}'";
	}
	
	$union_query = "(" . $union_query . ") ";
	
	// to get all possible combination of $i elements, make a select from $k unions from above. Keep only those rows that do not have duplicate values
	for($i = 1; $i <= $columns_count; ++$i) {
		// build a select from $i subqueries
		$query = "select * from " . $union_query . "_0";
		for($k = 1; $k < $i; ++$k) {
			$query .= ", " . $union_query . "_" . $k;
		}
		
		$result = mysqli_query($query) or die("Can not obtain the columns combinations: " . mysqli_error());
		while($row = mysqli_fetch_row($result)) {
			// keep only those rows that do not have duplicate values
			if(sizeof($row) == sizeof(array_unique($row))) {
				// the array has no duplicate values
				$combinations[] = $row;
			}
		}
		mysqli_free_result($result);
	}
	
	return $combinations;
	//print_r($combinations);
}

/* Gets the time in microseconds
*/
function getmicrotime(){ 
    list($usec, $sec) = explode(" ", microtime()); 
    return ((float)$usec + (float)$sec);
}

/* Runs a query 1000 times and returns the time taken for this.
*/
function clock_query($query, $link) {
	$start_time = getmicrotime();
	
	for($i = 0; $i < 1000; ++$i) {
		$result = mysqli_query($query, $link);
		if($result) {
			mysqli_free_result($result);
		}
	}
	
	$end_time = getmicrotime();
	
	return $end_time - $start_time;
}
?>

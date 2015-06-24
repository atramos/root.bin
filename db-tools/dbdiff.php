#!/usr/bin/php
<?php
# compare the schemas of two databases, outputting the necessary ALTER statements.
# usage:
#    dbdiff.php server1 user1 password1 db1 server2 user2 password2 db2
#
# Copyright (C)2009,2015 Zigabyte Corporation. See LICENSE file for restrictions.
#

class DB
{
	var $handle;
	var $prefix = '';
	var $name;

	//$db = DB::open('mysql', 'localhost', 'engine2' 'root', '');
	function& open($conf)
	{
		echo("Connect: server=$conf[0] user=$conf[1] dbname=$conf[3]\n");
		$db = new DB();
		$db->handle = mysqli_connect($conf[0], $conf[1], $conf[2], $conf[3]);

		if(!$db->handle) die("Can't connect : ".mysqli_error($db->handle)."\n");

		$db->name = $conf[1];
		if(isset($conf[4])) $db->prefix = $conf[4];

		if(!$db->handle->select_db($conf[3])) die("Can't use ".$conf[1].' : '.mysqli_error()."\n");

		return $db;
	}

	function close()
	{
		mysqli_close($this->handle);
	}

	function exec($query)
	{
		$res = $this->handle->query($query);

		//dbg_add('queries', $query);

		if(!$res) die($query."\n\t\t".mysqli_error($this->handle)."\n\n");

		return $res;
	}

	function quote($val)
	{
		return '`'.$val.'`';
	}

	function escape($val)
	{
		return '\''. $this->handle->escape_string($val).'\'';
	}

	function column_definition($field, $key = false)
	{
		$sql = $this->quote($field['Field']).' '.$field['Type'];
		if($field['Collation']) $sql.=' COLLATE '.$field['Collation'];
		if($field['Null']==='NO') $sql.=' NOT NULL';
		if($key && $field['Key']==='PRI') $sql.=' PRIMARY KEY';
		if(isset($field['Default'])) $sql.=' DEFAULT '.$this->escape($field['Default']);
		if(isset($field['Comment'])) $sql.=' COMMENT '.$this->escape($field['Comment']);
		if($field['Extra']) $sql.= ' '.$field['Extra'];

		return $sql;
	}

	function get_info()
	{
		$result = $this->exec("show variables like '%_database'");
		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			$columns[$row['Variable_name']] = $row['Value'];
		}

		return $columns;
	}

	function list_triggers()
	{
		$result = $this->exec('SHOW TRIGGERS LIKE \''.$this->prefix."%'");
		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			unset($row['Definer']);
			unset($row['Created']);
			$columns[$row['Trigger']] = $row;
		}

		return $columns;
	}

	function list_tables()
	{
		$result = $this->exec('SHOW TABLE STATUS LIKE \''.$this->prefix."%'");
		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			$columns[$row['Name']] = $row;
		}

		return $columns;
	}

	function list_funcs()
	{
		$result = $this->exec('SELECT SPECIFIC_NAME,ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_BODY,ROUTINE_DEFINITION,DTD_IDENTIFIER,EXTERNAL_NAME,EXTERNAL_LANGUAGE,PARAMETER_STYLE,IS_DETERMINISTIC,SQL_DATA_ACCESS FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA='.$this->escape($this->name));
		//$result = $this->exec('SHOW '.$var.' STATUS WHERE Db='.$this->escape($this->name));

		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			$columns[$row['ROUTINE_NAME']] = $row;
		}

		return $columns;
	}

	function list_index($table)
	{
		$result = $this->exec('SHOW INDEX FROM `'.$table.'`');
		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			unset($row['Null']);
			unset($row['Cardinality']);
			$columns[$row['Key_name']][$row['Column_name']] = $row;
		}

		return $columns;
	}

	function list_columns($table)
	{
		$result = $this->exec('SHOW FULL COLUMNS FROM `'.$table.'`');
		$columns = array();
		while($row=mysqli_fetch_assoc($result))
		{
			unset($row['Key']);
			unset($row['Privileges']);
			$columns[$row['Field']] = $row;
		}

		return $columns;
	}

	function get_create_sql($var, $name)
	{
		$result = $this->exec('SHOW CREATE '.$var.' '.$this->quote($name).'');
		$row=mysqli_fetch_assoc($result);
		return $row['Create '.ucfirst(strtolower($var))];
	}

	function diff($etalon)
	{
		ini_set('max_execution_time', 10000);

		$diff = '';
		$child_tables = $this->list_tables();
		$etalon_tables = $etalon->list_tables();

		$child_info = $this->get_info();
		$etalon_info = $etalon->get_info();

		$hasfk = false;

		if($child_info['collation_database'] != $etalon_info['collation_database'])
		{
			$diff .= 'ALTER DATABASE '.$this->quote($this->name).' DEFAULT CHARACTER SET '.$etalon_info['character_set_database'].' COLLATE '.$etalon_info['collation_database'] . ";\n";
		}

		foreach($etalon_tables as $tname=>$etalon_table)
		{
			if(!isset($child_tables[$tname])) // no table - create it
			{
				if(!$etalon_table['Engine']) // is VIEW
				{
					$diff .= $etalon->get_create_sql('view', $tname) . ";\n";
				}
				else
				{
					$diff .= $etalon->get_create_sql('table', $tname) . ";\n";
				}
			}
			else // table exists - check if it fits the model
			{
				if(!$etalon_table['Engine']) // is VIEW
				{
					if(!$child_tables[$tname]['Engine'])  // child is VIEW also - compare code
					{
						if( $etalon->get_create_sql('view', $tname) != $this->get_create_sql('view', $tname) )
						{
							$diff .= 'DROP VIEW '.$this->quote($tname) . ";\n";
							$diff .= $etalon->get_create_sql('view', $tname) . ";\n";
						}
					}
					else // child is normal table - drop it
					{
						$diff .= 'DROP TABLE '.$this->quote($tname) . ";\n";
						$diff .= $etalon->get_create_sql('view', $tname) . ";\n";
					}
				}
				else
				{
					/// CHARSET
					if($etalon_table['Collation'] != $child_tables[$tname]['Collation'])
					{
						$cs = explode('_', $etalon_table['Collation']);
						$diff .= 'ALTER TABLE '.$this->quote($tname).' DEFAULT CHARACTER SET '.$cs[0].' COLLATE '.$etalon_table['Collation'] . ";\n";
					}

					/// COLUMNS
					$etalon_cols = $etalon->list_columns($tname);
					$child_cols = $this->list_columns($tname);

					$pri_exists = false;

					foreach($child_cols as $fname=>$field) // drop the rest
					{
						if(!isset($etalon_cols[$fname])) // no such field in model - drop it
						{
							$diff .= 'ALTER TABLE '.$this->quote($tname).' DROP COLUMN '.$this->quote($fname) . ";\n";
							unset($child_cols[$fname]);
						}
					}

					$ecols = array_keys($etalon_cols);
					$ccols = array_keys($child_cols);

					foreach($ecols as $ei=>$fname)
					{
						if(!isset($child_cols[$fname]))
						{
							array_splice($ccols, $ei, 0, $fname);
						}
					}

					$alter_all = false;
					foreach($ecols as $ei=>$fname)
					{
						if($fname != $ccols[$ei])
						{
							$alter_all = $fname;
							break;
						}
					}

					$diff_later = '';

					$after = ' FIRST';
					foreach($etalon_cols as $fname=>$field)
					{
						if($alter_all === $fname) $alter_all = true;

						if(!isset($child_cols[$fname])) // no such field in db - add one
						{
							$diff .= 'ALTER TABLE '.$this->quote($tname).' ADD COLUMN '.$this->column_definition($field, true) . $after .";\n";

							if($field['Key'] === 'PRI') $pri_exists = true;
						}
						elseif($alter_all === true || $field != $child_cols[$fname] ) // wrong definition - change it
						{
							$d = 'ALTER TABLE '.$this->quote($tname).' CHANGE COLUMN '.$this->quote($fname).' '.$this->column_definition($field) . $after . ";\n";

							if($field['Extra'])
							{
								$diff_later .= $d;
							}
							else
							{
								$diff .= $d;
							}
						}

						$after = ' AFTER '.$this->quote($fname);
					}

					/// DROP INDEX
					$etalon_index = $etalon->list_index($tname);
					$child_index = $this->list_index($tname);

					foreach($child_index as $fname=>$fields) // drop the rest
					{
						$exists = false;
						foreach($fields as $field_name=>$field)
						{
							if(isset($etalon_cols[$field_name]))
							{
								$exists = true;
							}
						}

						if($exists && (!isset($etalon_index[$fname]) || $etalon_index[$fname] != $child_index[$fname]))
						{
							$diff .= 'ALTER TABLE '.$this->quote($tname).' DROP INDEX '.$this->quote($fname). ";\n";
							unset($child_index[$fname]);
						}
					}

					/// DROP FOREIGN KEYS
					if(preg_match_all('/CONSTRAINT\s+`?(.*?)`?\s+FOREIGN KEY(.*?)(?:\n|;)/i', $etalon->get_create_sql('table', $tname), $etalon_constrains, PREG_SET_ORDER)
					|| preg_match_all('/CONSTRAINT\s+`?(.*?)`?\s+FOREIGN KEY(.*?)(?:\n|;)/i', $this->get_create_sql('table', $tname), $child_constrains, PREG_SET_ORDER))
					{
						$hasfk = true;
					}

					foreach($child_constrains as $cc)
					{
						$found = false;
						foreach($etalon_constrains as $match)
						{
							if($match[1] == $cc[1] && $match[0] == $cc[0]) // found same constrain
							{
								$found = true;
								break;
							}
						}

						if(!$found) $diff .= 'ALTER TABLE '.$this->quote($tname).' DROP FOREIGN KEY '.$this->quote($cc[1]). ";\n";
					}

					/// ENGINE
					if($etalon_table['Engine'] != $child_tables[$tname]['Engine'])
					{
						$diff .= 'ALTER TABLE '.$this->quote($tname).' ENGINE = '.$etalon_table['Engine'] . ";\n";
					}

					/// CREATE INDEX
					foreach($etalon_index as $fname=>$fields)
					{
						if(!isset($child_index[$fname]))
						{
							$type = '';
							$prefix = '';
							$name = '';
							$fld = '';

							foreach($fields as $feild_name=>$field)
							{
								if($field['Index_type'] == 'FULLTEXT') $prefix = 'FULLTEXT';
								else $type = 'USING '.$field['Index_type'];

								if(!$field['Non_unique']) $prefix = 'UNIQUE';

								if($fname!='PRIMARY')
								{
									$name = $this->quote($fname);
								}
								else
								{
									if($pri_exists) continue;
									$prefix = 'PRIMARY';
								}

								if($field['Sub_part']) $part = ' ('.$field['Sub_part'].') '; else $part = '';
								if($fld) $fld .= ',';
								$fld .= $this->quote($field['Column_name']).$part;
							}

							$diff .= 'ALTER TABLE '.$this->quote($tname).' ADD '.$prefix.' KEY '.$name.' '.$type.' ('.$fld.')'. /*' COMMENT '.$this->escape($field['Comment']).*/ ";\n";
						}
					}

					$diff .= $diff_later;

					/// CREATE FOREIGN KEYS
					foreach($etalon_constrains as $match)
					{
						$found = false;
						foreach($child_constrains as $cc)
						{
							if($match[1] == $cc[1] && $match[0] == $cc[0]) // found same constrain
							{
								$found = true;
								break;
							}
						}

						if(!$found) $diff .= 'ALTER TABLE '.$this->quote($tname).' ADD '.$match[0]. ";\n";
					}
				}
			}
		}

		foreach($child_tables as $tname=>$child_table)
		{
			if(!isset($etalon_tables[$tname]))
			{
				if(!$child_table['Engine']) // is VIEW
				{
					$diff .= 'DROP VIEW '.$this->quote($tname) . ";\n";
				}
				else
				{
					$diff .= 'DROP TABLE '.$this->quote($tname) . ";\n";
				}
			}
		}

		/// PROCS & FUNCS
		$child_procs = $this->list_funcs();
		$etalon_procs = $etalon->list_funcs();

		foreach($child_procs as $fname=>$field) // drop the rest
		{
			if( !isset($etalon_procs[$fname]) || $etalon_procs[$fname] != $child_procs[$fname])
			{
				$diff .= 'DROP '.$field['ROUTINE_TYPE'].' '.$this->quote($fname). ";\n";
				unset($child_procs[$fname]);
			}
		}

		foreach($etalon_procs as $fname=>$field)
		{
			if(!isset($child_procs[$fname]))
			{
				$diff .= "delimiter //\n";
				$diff .= $etalon->get_create_sql($field['ROUTINE_TYPE'], $fname) . "//\n";
				$diff .= "delimiter ;\n";
			}
		}

		/// TRIGGERS
		$child_procs = $this->list_triggers();
		$etalon_procs = $etalon->list_triggers();

		foreach($child_procs as $fname=>$field) // drop the rest
		{
			// drop only if the table should exist else it would be dropped with the table
			if( isset($etalon_tables[$field['Table']]) && (!isset($etalon_procs[$fname]) || $etalon_procs[$fname] != $child_procs[$fname]) )
			{
				$diff .= 'DROP TRIGGER '.$this->quote($fname). ";\n";
				unset($child_procs[$fname]);
			}
		}

		foreach($etalon_procs as $fname=>$field)
		{
			if(!isset($child_procs[$fname]))
			{
				$diff .= "delimiter //\n";
				$diff .= 'CREATE TRIGGER '.$this->quote($fname).' '.$field['Timing'].' '.$field['Event'].' ON '.$this->quote($field['Table']).' FOR EACH ROW '.$field['Statement']. "//\n";
				$diff .= "delimiter ;\n";
			}
		}

		if($diff && $hasfk) $diff = "SET foreign_key_checks = 0;\n".$diff."SET foreign_key_checks = 1;\n";

		return $diff;
	}

}

if($_SERVER['argv'][1] == '--help' || $_SERVER['argv'][1] == '-h' || count($_SERVER['argv'])==1)
{
	echo "\nUsage 1: dbdiff.php <host1>[:<port1>] <db1> <user1> <pass1>  <host2>[:<port2>] <db2> <user2> <pass2>\n\n";
    echo "\nUsage 2: dbdiff.php <db1> <db2> # requires whoami and \$HOME/.my.cnf\n\n";
	exit;
}

if(count($_SERVER['argv']) == 3) {
	$ini = parse_ini_file(getenv('HOME') . '/.my.cnf', true);
	if(!$ini) die ("error: ini file");
	$user = trim(`whoami`);
    if(!$user) die ("error: no result from whoami");
	$pass = $ini['mysql']['password'];
	$db1_params = array('localhost:3306', $_SERVER['argv'][1], $user, $pass);
	$db2_params = array('localhost:3306', $_SERVER['argv'][2], $user, $pass);
}
else {
    $db1_params = array_slice($_SERVER['argv'], 1, 4);
    $db2_params = array_slice($_SERVER['argv'], 5, 4);
}

$db1 = DB::open($db1_params);
$db2 = DB::open($db2_params);

echo $db2->diff($db1);

$db1->close();
$db2->close();
?>

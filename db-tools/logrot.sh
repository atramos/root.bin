# #!/bin/bash -e
 
# select TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME from information_schema.tables LEFT OUTER JOIN (select * from information_schema.columns WHERE DATA_TYPE in ('timestamp','datetime')) t1 USING (TABLE_SCHEMA, TABLE_NAME) where TABLE_NAME like 'log%' and TABLE_TYPE = 'BASE TABLE';
# +--------------+--------------------+-------------+
# | TABLE_SCHEMA | TABLE_NAME         | COLUMN_NAME |
# +--------------+--------------------+-------------+
# | ctcweb       | log_mail           | created     |
# | ctcweb       | log_propertydetail | date        |
# | ctcweb       | log_searchcriteria | date        |
# | logs         | log_access         | ts          |
# | logs         | log_access         | server_ts   |
# | logs         | log_application    | dbTimeStamp |
# | ctcweb       | log_showings       | NULL        |
# | ctcweb       | log_useragent      | NULL        |
# | ctcweb       | log_web_visitor    | NULL        |
# +--------------+--------------------+-------------+
# 

CUTOFF=$(date '+%Y-%m-%d 00:00:00')
BUCKET=$1

dump() {
  DATABASE=$1
  TABLE=$2
  COLUMN=$3
  echo "Started: $DATABASE.$TABLE"
  while [ $(mysql --column-names=FALSE --batch -e "select count(*) from $DATABASE.$TABLE where $COLUMN < '$CUTOFF'") -gt 0 ]
  do
	  FILE=$(date -Is).csv.gz
	  echo $FILE
	  mysql --max_allowed_packet=128M --batch -e "select * from $DATABASE.$TABLE where $COLUMN < '$CUTOFF' limit 100000" | gzip | aws s3 cp - s3://$BUCKET/logs/$DATABASE.$TABLE/$FILE
	  mysql -e "delete from $DATABASE.$TABLE where $COLUMN < '$CUTOFF' limit 100000"
  done
  echo "Done   : $DATABASE.$TABLE"
}

dump logs log_application dbTimeStamp
dump logs log_access ts
dump ctcweb log_propertydetail date
dump ctcweb log_searchcriteria date


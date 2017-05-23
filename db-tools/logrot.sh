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
  FILE=$(date -Is).xml.gz
  mysqldump --xml --max_allowed_packet=64M $DATABASE $TABLE --where "$COLUMN < '$CUTOFF'" | gzip | aws s3 cp - s3://$BUCKET/logs/$DATABASE.$TABLE/$FILE
  while [ $(mysql --column-names=FALSE --batch -e "select count(*) from $DATABASE.$TABLE where $COLUMN < '$CUTOFF'") -gt 0 ]
  do
	echo deleting...
	mysql -e "delete from $DATABASE.$TABLE where $COLUMN < '$CUTOFF' limit 500000"
	mysql -e "purge binary logs before now()"
  done
  echo "Done   : $DATABASE.$TABLE"
}

dump logs log_application dbTimeStamp
dump logs log_access ts
dump ctcweb log_propertydetail date
dump ctcweb log_searchcriteria date
dump ctcweb log_mail created



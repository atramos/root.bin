#!/bin/bash

select TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME from information_schema.tables LEFT OUTER JOIN (select * from information_schema.columns WHERE DATA_TYPE in ('timestamp','datetime')) t1 USING (TABLE_SCHEMA, TABLE_NAME) where TABLE_NAME like 'log%' and TABLE_TYPE = 'BASE TABLE';
+--------------+--------------------+-------------+
| TABLE_SCHEMA | TABLE_NAME         | COLUMN_NAME |
+--------------+--------------------+-------------+
| ctcweb       | log_mail           | created     |
| ctcweb       | log_propertydetail | date        |
| ctcweb       | log_searchcriteria | date        |
| logs         | log_access         | ts          |
| logs         | log_access         | server_ts   |
| logs         | log_application    | dbTimeStamp |
| ctcweb       | log_showings       | NULL        |
| ctcweb       | log_useragent      | NULL        |
| ctcweb       | log_web_visitor    | NULL        |
+--------------+--------------------+-------------+


#!/bin/bash

export AWS_DEFAULT_REGION=us-east-2

LOGROT=$(dirname $0)/db-tools/logrot.sh

TARGET="s3://ctcdb.chicagotopcondos.com/mysql-backup/mysql-backup.$(date '+%Y-%m-%d').sql.gz"

aws sns publish \
	--topic-arn arn:aws:sns:us-east-2:984073016564:cron \
	--message file://<(sh -c "exec 2>&1; \
				 $LOGROT; \
				 mysqldump -A -R --single-transaction --master-data=2 --max_allowed_packet=64M |\
				 dd |\
				 gzip |\
				 aws s3 cp - $TARGET") >> /var/log/sns.log


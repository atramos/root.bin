#!/bin/sh

/etc/init.d/mysql stop
killall mysqld

mysqld --skip-grant-tables &
sleep 5

mysql -u root <<EOF
UPDATE mysql.user SET Password=PASSWORD('$1') WHERE User='root';
FLUSH PRIVILEGES;
EOF

kill %1
sleep 5

cat > .my.cnf <<EOF
[client]
password = $1
EOF

/etc/init.d/mysql restart

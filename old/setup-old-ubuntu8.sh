#
# Development server settings for CTC - Ubuntu8 - Jan 25, 2009.
#  Modified for local desktop linux
#
export DEBIAN_FRONTEND=noninteractive

cat >> /etc/apt/sources.list <<EOF
deb http://us.archive.ubuntu.com/ubuntu/ dapper universe
deb http://security.ubuntu.com/ubuntu dapper-security universe
EOF

apt-get update

ntpdate ntp.org
apt-get -y install apache2
apt-get -y install php5-mysql
apt-get -y install mysql-client
apt-get -y install man
apt-get -y install make
apt-get -y install gcc
apt-get -y install g++
apt-get -y install ncftp
apt-get -y install mailx
apt-get -y install wget
apt-get -y install curl
apt-get -y install zip
apt-get -y install lynx
apt-get -y install php5-cli
apt-get -y install libapache2-mod-perl2
apt-get -y install libmysqlclient12-dev

perl -lpi -e 's/^#alias/alias/' ~/.bashrc
cat >>~/.bashrc <<EOF
PATH=$PATH:/usr/local/jdk/bin
export DISPLAY=`who|perl -lne '/^root.*\((.*)\)/&&print $1'`:0
EOF

cd /usr/local
curl -s http://zigabyte.com/tools/jdk.tgz | tar -zxf - &

(cd /etc/apache2/mods-enabled && ln -s ../mods-available/rewrite.load .)

apt-get -y install smbfs
apt-get -y install samba
cat <<EOF >/etc/samba/smb.conf
[global]
   workgroup = WORKGROUP
   server string = %h server (Samba, Ubuntu)
   dns proxy = no
   log file = /var/log/samba/log.%m
   max log size = 1000
   syslog = 0
   panic action = /usr/share/samba/panic-action %d
   security = user
   encrypt passwords = true
   smb passwd file = /etc/samba/smbpasswd
   passdb backend = tdbsam
   socket options = TCP_NODELAY
   usershare allow guests = no

[home]
   comment = Home Dirs
   browseable = yes
   read only = no
   path = /home/%U
   invalid users = root

[root]
   comment = Root Directory
   browseable = yes
   valid users = root
   read only = no
   path = /
EOF

apt-get -y install rsync
apt-get -y install mmv
apt-get -y install perl-doc

# PHP APC
apt-get -y install php-pear
apt-get -y install php5-dev
apt-get -y install apache2-dev 
pecl install apc
echo "extension=apc.so" > /etc/php5/apache2/conf.d/apc.ini 
/etc/init.d/apache2 restart

echo America/Chicago > /etc/timezone
dpkg-reconfigure -u tzdata

apt-get -y install mysql-server
apt-get -y install phpmyadmin

apt-get -y install postfix
apt-get -y install proftpd

echo 'Enter password for smb/root:'
smbpasswd -a root

perl -MCPAN -e shell
perl -MCPAN -e 'install Bundle::CPAN' < /dev/null
perl -MCPAN -e 'install Date::Parse' < /dev/null
perl -MCPAN -e 'install "Term::ReadKey"' < /dev/null
perl -MCPAN -e 'install Digest::SHA'


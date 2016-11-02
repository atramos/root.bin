#!/bin/bash

EC2_AVAIL_ZONE=`curl -s http://169.254.169.254/latest/meta-data/placement/availability-zone`
EC2_REGION="`echo \"$EC2_AVAIL_ZONE\" | sed -e 's:\([0-9][0-9]*\)[a-z]*\$:\\1:'`"

volume=$(aws ec2 --region $EC2_REGION describe-volumes |jq -r '.Volumes[].Attachments[]|select(.Device == "/dev/sdh").VolumeId')

service mysql stop
umount /var/lib/mysql

aws ec2 --region $EC2_REGION create-snapshot --volume-id $volume --description "mysql_volume_$volume" |tee snap.out

mount /var/lib/mysql
service mysql start


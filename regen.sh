#!/bin/bash

DBNAME='civicrm'

cd ../sql
mysqladmin -f drop $DBNAME
mysqladmin create $DBNAME
mysql $DBNAME < civicrm.mysql
mysql $DBNAME < civicrm_data.mysql
mysql $DBNAME < civicrm_sample.mysql
mysql $DBNAME < zipcodes.mysql
php GenerateData.php
mysql $DBNAME -e 'DROP TABLE zipcodes; UPDATE civicrm_domain SET config_backend = NULL'
mysqldump -cent $DBNAME > civicrm_generated.mysql
#cat civicrm_sample_report.mysql >> civicrm_generated.mysql
cat civicrm_sample_custom_data.mysql >> civicrm_generated.mysql
#cat civicrm_devel_config.mysql >> civicrm_generated.mysql
cat ../CRM/Case/xml/configuration.sample/SampleConfig.mysql >> civicrm_generated.mysql
cd -

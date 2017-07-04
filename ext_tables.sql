#
# Table structure for table 'tx_schedulerreports_domain_model_taskconfiguration'
#
CREATE TABLE tx_schedulerreports_domain_model_taskconfiguration (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	task_uid int(11) NOT NULL,
	maximum_execution_time int(11) DEFAULT '0' NOT NULL,
	maxiumm_delay int(11) DEFAULT '0' NOT NULL,

	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY task_uid (task_uid)

);

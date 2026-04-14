CREATE TABLE tx_aifilemetadata_token_usage (
	model varchar(255) DEFAULT '' NOT NULL,
	input_tokens int(11) DEFAULT 0 NOT NULL,
	output_tokens int(11) DEFAULT 0 NOT NULL,
	total_tokens int(11) DEFAULT 0 NOT NULL,
	context varchar(255) DEFAULT '' NOT NULL,
	file_uid int(11) DEFAULT 0 NOT NULL,
	be_user_uid int(11) DEFAULT 0 NOT NULL,
	locale varchar(20) DEFAULT '' NOT NULL
);

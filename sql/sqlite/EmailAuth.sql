-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: EmailAuth.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/emailauth (
  email VARCHAR(255) NOT NULL,
  PRIMARY KEY(email)
);

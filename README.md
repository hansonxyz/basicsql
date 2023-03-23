# php-basic-sql

A simple generic PHP class for interfacing with MySQL

This is a PHP class that provides the bare minimum interface to connect and perform MySQL queries in a way which is friendly to the rest of a simple PHP application.

Create a new connection with:

`$sql = new BasicSQL("host.name", "username", "password", "databasename");`

Then perform queries using:

`$sql->query("select * from mytable where mycolumn = :val", ['val' => 'bananna']);`

You can use True, False, and NULL as values, these will be converted to 1, 0, and undefined when saving to the db.

Also available is functions fetch_all, fetch, and fetch_one, which will return an array of rows, a single row, or a single value respectively.

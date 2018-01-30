# PDO wrapper for really easy queries to the database

Useful wrapper for PDO using php7.1

Database\DB class realizes a singleton/multiton desing pattern.

### Table example

```mysql
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `role` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL
  PRIMARY KEY  (`id`)
);

INSERT INTO `users` (`id`, `role`, `name`, `email`) VALUES
(1, 'admin', 'John Smith', 'js@gmail.com'),
(2, 'user', 'Barbara Johnson', 'bj@gmail.com'),
(3, 'manager', 'Mary Lee', 'mee@gmail.com'),
(4, 'user', 'Lucia Woods', 'uw@gmail.com'),
(5, 'user', 'Brandon Alister', 'ba@gmail.com');
```

### Get instance
```php
use Database\DB;
use Database\DBExpression;

$db = DB::getInstanse();
```

### Select
```php
$data = $db->selectAll('users', ['role' => 'admin']);
```

### Select row
```php
$data = $db->selectRow('users', ['email' => 'admin@gmail.com']);
```

### Select row by id
```php
$data = $db->selectRowById('users', 3);
```

### Select column
```php
$data = $db->selectColumn('users', 'email');
```

### Select cell
```php
$data = $db->selectColumn('users', 'email', ['id' => 3]);
```

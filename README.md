## 📄 Fichier #12: `README.md`

```markdown
# ⚡ Quant

**NoSQL-like syntax, SQL-powered ORM for PHP**

Quant is a lightweight PHP ORM that gives you a **NoSQL-like syntax** while generating **optimized SQL** behind the scenes. Write clean, intuitive queries without worrying about SQL complexity.

---

## ✨ Features

- 🎯 **NoSQL-like syntax** - Write queries like `quant('users')->where(['status' => 'active'])->fetch()`
- 🗄️ **Multi-database support** - MySQL, PostgreSQL, SQLite
- ⚡ **Simple & lightweight** - No bloated dependencies
- 🔒 **Secure** - Automatic parameter binding prevents SQL injection
- 💾 **Built-in cache** - Query caching with TTL
- 📦 **Collection utilities** - Rich methods for data manipulation
- 🔄 **Transactions** - Full transaction support with savepoints

---

## 📦 Installation

```bash
composer require dovstone0911/quant
```

---

## 🚀 Quick Start

```php
<?php

use Quant\Database\Config;

// 1. Configure your database
Config::set([
    'driver' => 'mysql',      // mysql, pgsql, sqlite
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret'
]);

// 2. Start querying with NoSQL-like syntax!

// Find users
$users = quant('users')->fetch([
    'where' => ['status' => 'active'],
    'limit' => 10,
    'orderBy' => ['created_at', 'DESC']
]);

// Fluent style
$users = quant('users')
    ->where(['status' => 'active'])
    ->whereGt('age', 18)
    ->orderBy('name')
    ->limit(10)
    ->fetch();

// Insert
$id = quant('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);

// Update
quant('users')
    ->where(['id' => 1])
    ->update(['status' => 'inactive']);

// Delete
quant('users')
    ->where(['status' => 'inactive'])
    ->delete();
```

---

## 📖 Documentation

### Basic CRUD

```php
// READ - Fetch all
$users = quant('users')->fetch();

// READ - With conditions
$users = quant('users')->fetch([
    'where' => ['status' => 'active'],
    'limit' => 10
]);

// READ - First result
$user = quant('users')
    ->where(['id' => 1])
    ->first();

// READ - Count
$total = quant('users')
    ->where(['status' => 'active'])
    ->count();

// READ - Pluck column
$emails = quant('users')->pluck('email');

// CREATE - Single
$id = quant('users')->insert([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);

// CREATE - Batch
$ids = quant('users')->insertBatch([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com']
]);

// UPDATE
$affected = quant('users')
    ->where(['status' => 'pending'])
    ->update(['status' => 'active']);

// DELETE
$affected = quant('users')
    ->where(['status' => 'inactive'])
    ->delete();
```

### Conditions

```php
// Simple equality
quant('users')->where(['status' => 'active']);

// Multiple conditions
quant('users')->where([
    'status' => 'active',
    'age' => 25
]);

// Greater than
quant('users')->whereGt('age', 18);

// Less than
quant('users')->whereLt('age', 65);

// LIKE
quant('users')->whereLike('name', '%john%');

// IN
quant('users')->whereIn('role', ['admin', 'moderator']);

// NOT IN
quant('users')->whereNotIn('status', ['deleted', 'banned']);

// NULL
quant('users')->whereNull('deleted_at');

// NOT NULL
quant('users')->whereNotNull('email_verified_at');

// BETWEEN
quant('users')->whereBetween('age', [18, 65]);

// Chain them!
$users = quant('users')
    ->where(['status' => 'active'])
    ->whereGt('age', 18)
    ->whereIn('role', ['admin', 'moderator'])
    ->whereLike('name', '%john%')
    ->fetch();
```

### Sorting & Limits

```php
// Order by
quant('users')->orderBy('name', 'ASC');
quant('users')->orderByDesc('created_at');

// Multiple order by
quant('users')
    ->orderBy('status', 'ASC')
    ->orderBy('created_at', 'DESC');

// Random order
quant('users')->inRandomOrder();

// Limit
quant('users')->limit(10);

// Offset
quant('users')->limit(10)->offset(20);

// Pagination helper
quant('users')->page(2, 15); // page 2, 15 per page
```

### Select

```php
// Select specific columns
quant('users')->select(['id', 'name', 'email']);

// Add more columns
quant('users')
    ->select(['id', 'name'])
    ->addSelect(['email', 'created_at']);

// Distinct
quant('users')->distinct()->select(['status']);
```

### Cache

```php
// Cache for 1 hour
$users = quant('users')
    ->cache(3600)
    ->where(['status' => 'active'])
    ->fetch();

// Cache with custom TTL
$users = quant('users')
    ->cache(300) // 5 minutes
    ->fetch();

// Clear cache
quant('users')->clearCache();
```

### Transactions

```php
use Quant\Database\Connection;

Connection::beginTransaction();

try {
    $id = quant('users')->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    quant('profiles')->insert([
        'user_id' => $id,
        'bio' => 'Hello world'
    ]);

    Connection::commit();
} catch (\Exception $e) {
    Connection::rollback();
    throw $e;
}
```

### Collections

```php
$collection = quant('users')->get(['where' => ['status' => 'active']]);

// Filter
$admins = $collection->where('role', 'admin');

// Pluck
$emails = $collection->pluck('email');

// Map
$names = $collection->map(fn($user) => strtoupper($user['name']));

// Group
$grouped = $collection->groupBy('role');

// Sum / Avg
$totalAge = $collection->sum('age');
$avgAge = $collection->avg('age');

// Sort
$sorted = $collection->sortBy('name');

// First / Last
$first = $collection->first();
$last = $collection->last();

// Slice
$page = $collection->slice(0, 10);

// Convert
$array = $collection->toArray();
$json = $collection->toJson();
```

### Raw SQL

```php
// Get generated SQL (debug)
$sql = quant('users')
    ->where(['status' => 'active'])
    ->toSql();

// Raw order by
quant('users')->orderByRaw('RAND()');

// Raw where (coming soon)
```

---

## 🗄️ Supported Databases

| Driver | Status |
|--------|--------|
| MySQL | ✅ Full support |
| PostgreSQL | ✅ Full support |
| SQLite | ✅ Full support |
| SQL Server | 🚧 Coming soon |

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

MIT License - see [LICENSE](LICENSE) for details.

---

## 👨‍💻 Author

**Dov Stone** - [dovstone0911](https://github.com/dovstone0911)

---

⭐ Star this repo if you like it!
```

---

Prêt pour le fichier #13 ?
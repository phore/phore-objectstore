# ObjectStore - Wrapper for various cloud based buckets

## Install

```
composer requre phore/objectstore
```

## Basic usage

```php
$store = new ObjectStore(new GoogleCloudStoreDriver(__DIR__ . "/file/to/identity.json", "bucketName"));

$store->object("object/some.json")->put("Some Data");

if ($store->has("object/some.json"))
    echo "Object existing";

echo $store->object("object/some.json")->get();
```


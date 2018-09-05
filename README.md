# Cloudstore - Wrapper for various cloud based buckets

## Install

```
composer requre phore/cloudstore
```

## Basic usage

```php
$store = new ObjectStore(new GoogleCloudStoreDriver(__DIR__ . "/file/to/identity.json", "bucketName"));

$store->put("object/some.json", "Some Data");

if ($store->has("object/some.json"))
    echo "Object existing";

echo $store->get("object/some.json");
```


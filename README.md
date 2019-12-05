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



## Develop

The google native drivers require a secret as service account. Create the secret using 

```
./kickstart.sh secrets edit google_test
```

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


## Driver

The object store can be created with

```php
$objectStore = ObjectStore::Connect('gcs://some-bucket?keyfile=/run/secrets/xyz');
```

Available Drivers:

| driver | driver class | example |
|--------|-------------|---------|
| Google Bucket                                                                 | `PhoreGoogleCloudStoreDriver`     | `gcs://<bucket-name>?keyfile=/run/secrets/google-key-1` |
| Google Bucket Native Driver (Requires `google/cloud-storage`)                 | `GoogleCloudStoreDriver`          | `gcsnd://<bucket-name>?keyfile=/run/secret/google-key-1` |
| Azure Block Storage                                                           | `--`          | `azbs://<bucket-name>?account=<account>&keyfile=/run/secrets/az-key-1` |
| Azure Block Storage Native Driver (Requires `microsoft/azure-storage-blob`)   | `AzureObjectStoreDriver`          | `azbsnd://<bucket-name>?account=<account>&keyfile=/run/secrets/az-key-1` |
| Filesystem driver                                                             | `FileSystemObjectStoreDriver`     | `file://path/` |
     



## Develop

The google native drivers require a secret as service account. Create the secret using 

```
./kickstart.sh secrets edit google_test
```

# Object Store Configuration

Object Store provides a general interface for Filesystem, MongoDB, GoogleCloud Storage, Azure Blob, AWS S3.

It can be configured using a simple URI scheme

Available Drivers:

| driver | driver class | example |
|--------|-------------|---------|
| Google Bucket                                                                 | `PhoreGoogleCloudStoreDriver`     | `gcs://<bucket-name>?keyfile=/run/secrets/google-key-1` |
| Google Bucket Native Driver (Requires `google/cloud-storage`)                 | `GoogleCloudStoreDriver`          | `gcsnd://<bucket-name>?keyfile=/run/secrets/google-key-1` |
| Azure Block Storage                                                           | `--`          | `azbs://<bucket-name>?account=<account>&keyfile=/run/secrets/az-key-1` |
| Azure Block Storage Native Driver (Requires `microsoft/azure-storage-blob`)   | `AzureObjectStoreDriver`          | `azbsnd://<bucket-name>?account=<account>&keyfile=/run/secrets/az-key-1` |
| AWS S3 Block Storage Native Driver (Requires `aws/aws-sdk-php`)               | `S3ObjectStoreDriver`             | `s3nd://<bucket-name>?account=<accountId>&keyfile=/run/secrets/keyfile&region=<region>` |
| Filesystem driver                                                             | `FileSystemObjectStoreDriver`     | `file://path/` |
   


## Examples

### AWS S3 Driver (Native Driver)

Available options:
- ***`account` (required)***: The IAM Account Key
- ***`region` (required)***: The region (e.g. `eu-central-1`)
- ***`keyfile` (optional)***: The secret key to load from a file (secrets storage)
- ***`secretkey` (optional)***: Specify the secret key directly (take care not to expose the uri!)

```
s3nd://<bucket-name>?account=<accountId>&keyfile=/run/secrets/keyfile&region=<region>
```
***Example***

```
s3nd://some-bucket_name?account=02ihekuzejlslieh&keyfile=/run/secrets/keyfile&region=eu-central-1
```

### Azure Blob Storage Driver (Native Driver)
Available options:
- ***`account` (required)***: The IAM Account Key
- ***`keyfile` (optional)***: The secret key to load from a file (secrets storage)
- ***`secretkey` (optional)***: Specify the secret key directly (take care not to expose the uri!)

```
azbsnd://<bucket-name>?account=<accountId>&keyfile=/run/secrets/keyfile
```

### GoogleCloud Driver (Native Driver)
Available options:
- ***`keyfile` (optional)***: The secret identity file (json-format) to from a file (secrets storage)

```
gcsnd://<bucket-name>?keyfile=/run/secrets/keyfile
```

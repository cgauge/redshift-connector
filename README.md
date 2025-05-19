# Laravel Redshift Connector

This library provides a custom database driver for Laravel to connect seamlessly with **Amazon Redshift**. It allows proper integration with Redshift’s PostgreSQL-compatible interface, with support for custom connection behaviors and optional AWS Secrets Manager integration.

## Inspiration

This library was inspired on [Laravel Aurora Connector](https://github.com/cgauge/laravel-aurora-connector/) 

## 🚀 Installation

```bash
composer customergauge/redshift
```

## ⚙️ Usage

In your `config/database.php`, define a connection using the `redshift` driver:

```php
'redshift' => [
    'driver' => 'redshift',
    'host' => env('REDSHIFT_HOST'),
    'port' => env('REDSHIFT_PORT', 5439),
    'database' => env('REDSHIFT_DATABASE'),
    'username' => env('REDSHIFT_USERNAME'),
    'password' => env('REDSHIFT_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'options' => [],
    'redshift' => [
        'secret' => env('AWS_REDSHIFT_SECRET'), // optional
    ],
],
```

You can optionally configure a secret name for **AWS Secrets Manager** to securely fetch the credentials.

## 🌍 AWS Configuration

Make sure you define your AWS region in `config/aws.php`, especially if using Secrets Manager:

```php
return [
    'region' => env('AWS_REGION', 'eu-west-1'),
    // other AWS services config
];
```

## 📦 Secrets Manager Support

This package can integrate with [AWS Secrets Manager](https://aws.amazon.com/secrets-manager/) to load your Redshift credentials at runtime.

To improve performance, we recommend using the [AWS Secrets Manager Caching Extension](https://github.com/cgauge/aws-secretsmanager-caching-extension).

## 🧪 Example Query

Once configured, you can use Eloquent or Query Builder as usual:

```php
DB::connection('redshift')->table('events')->select('user_id')->limit(10)->get();
```

## 🤝 Contributing

We welcome contributions! Feel free to open issues, suggest improvements, or submit PRs.

## 📝 License

Laravel Redshift Connector is licensed under the **MIT License**.

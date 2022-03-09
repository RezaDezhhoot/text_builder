# TextBuilder

TextBuilder is a Laravel package for create various dynamic texts.

## Installation

Use the package manager [composer](https://getcomposer.org/) to install TextBuilder.

```bash
composer require ermac/text_builder
```
Put provider inside the config/app.php file and Service Providers section
```php
'providers' => [    
    ...
    'TextBuilder' => Ermac\TextBuilder\TextBuilderFacade::class,
```

And then enter in the alias class 
```php
'aliases' => [    
    ...
    Ermac\TextBuilder\TextBuilderServiceProvider::class,
```
And run command
```bash
php artisan vendor:publish --provider="Ermac\TextBuilder\TextBuilderServiceProvider" --tag=config
```
## Configuration
Inside the config path and textBuilder.php file
You can specify the symbol between which your parameters are to be placed
```php
'sign' => '%'
```
You can also define parameters globally and explain each one
```php
'global_parameters' => [
        'date' => 'description',
        'time',
    ]
```

## Usage
In the model where you want to define parameters, first call the HasParams trait
```php
use Ermac\TextBuilder\HasParams;
class User extends Authenticatable
{
    ...
    use HasParams;
}
```
### Parameters
In the model where you want to define parameters, first call the params property, then enter the desired parameters in the params property. Note that the defined parameters must be the same as the names of the columns in the database table.
```php
protected $params = [
    'name' => 'description',
    'email',
     ];
```
If you want to enter all the columns, you can use the * sign
```php
protected $params = [
    '*'
     ];
```
And if you want to ignore a number of columns, you can use the ^ symbol.
```php
protected $params = [
    '^password,created_at'
     ];
```
### Relations 
You can use one-to-one or one-to-many or many-to-many relationship methods to configure such parameters.

#### one-to-one 
```php
protected $params = [
       'wallet-balance',
    ];

public function wallet()
{
    return $this->hasOne(Wallet::class);
}
```
#### one-to-many
```php
protected $params = [
       'orders-tracking_code',
       'orders-price',
       'orders-product-title',
    ];

public function orders()
{
    return $this->hasMany(Order::class);
}
```
You can use different relationships to infinity
##### Order.php
```php
class Order extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```
#### many-to-many
```php
protected $params = [
      'roles-name'
    ];

public function roles()
{
    return $this->belongsToMany(Role::class,'user_has_roles');
}
```
## Methods
```php
$text = "Hi %users_name%, your wallet balance is $ %wallet-balance%";
$text = TextBuilder::make($text,\App\Models\User::find(1) );
```
output
```php
"Hi james, your wallet balance is $ 1000";
```
You can use this mode if you need to enter multiple models
```php
$text = TextBuilder::make($text,[\App\Models\User::find(1),\App\Models\Order::find(1)] );
```
And if you need to ignore a parameter, you can use this mode
```php
$text = TextBuilder::make($text,[\App\Models\User::find(1),\App\Models\Order::find(1)] ,['users_password',...]);
```
If you need to manually enter the parameters like the global parameters, you can use the following method
```php
$text = TextBuilder::set($text,['time',...],[\Carbon\Carbon::make(now())->format('H:m'),...]);
```

## License
[MIT](https://choosealicense.com/licenses/mit/)

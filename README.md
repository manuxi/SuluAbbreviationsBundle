# SuluAbbreviationsBundle!
![php workflow](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
<a href="https://github.com/manuxi/SuluAbbreviationsBundle/tags" target="_blank">
<img src="https://img.shields.io/github/v/tag/manuxi/SuluAbbreviationsBundle" alt="GitHub license">
</a>

I made this bundle to have the possibility to manage abbreviations in my projects.
The abbreviations are translatable. Please feel comfortable submitting feature requests. 
This bundle is still in development. Use at own risk 🤞🏻


## 👩🏻‍🏭 Installation
Install the package with:
```console
composer require manuxi/sulu-abbreviations-bundle
```
If you're *not* using Symfony Flex, you'll also
need to add the bundle in your `config/bundles.php` file:

```php
return [
    //...
    Manuxi\SuluAbbreviationsBundle\SuluAbbreviationsBundle::class => ['all' => true],
];
```
Please add the following to your `routes_admin.yaml`:
```yaml
SuluAbbreviationsBundle:
    resource: '@SuluAbbreviationsBundle/Resources/config/routes_admin.yml'
```
Last but not least the schema of the database needs to be updated.  

Some tables will be created (prefixed with app_):  
abbreviations, abbreviations_translation.  

See the needed queries with
```
php bin/console doctrine:schema:update --dump-sql
```  
Update the schema by executing 
```
php bin/console doctrine:schema:update --force
```  

Make sure you only process the bundles schema updates!

## 🎣 Usage
First: Grant permissions for abbreviations. 
After reload you should see the abbreviations item in the navigation. 
Start to create abbreviations.
Use smart_content property type to show a list of abbreviations, e.g.:
```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="en">Abbreviations</title>
        <title lang="de">Abbreviations</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```
Example of the corresponding twig template for the abbreviations list:
```html
{% for abbreviation in abbreviations %}
    <div class="col">
        <h2>
            {{ abbreviation.name }}
        </h2>
        <p>
            {{ abbreviation.explanation|raw }}
        </p>
    </div>
{% endfor %}
```

## 🧶 Configuration
There exists no configuration.

## 👩‍🍳 Contributing
For the sake of simplicity this extension was kept small.
Please feel comfortable submitting issues or pull requests. As always I'd be glad to get your feedback to improve the extension :).

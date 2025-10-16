# SuluAbbreviationsBundle!
![php workflow](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
<a href="https://github.com/manuxi/SuluAbbreviationsBundle/tags" target="_blank">
<img src="https://img.shields.io/github/v/tag/manuxi/SuluAbbreviationsBundle" alt="GitHub license">
</a>

I made this bundle to have the possibility to manage abbreviations in my projects.

This bundle contains
- Several filters for Abbreviations Content Type
- Link Provider
- Sitemap Provider
- Handler for Trash Items
- Handler for Automation
- Possibility to assign a contact as author
- Twig Extension for resolving Abbreviations / get a list of Abbreviations
- Events for displaying Activities
- Search indexes
    - refresh whenever entity is changed
    - distinct between normal and draft
and more...

The abbreviations are translatable.

Please feel comfortable submitting feature requests. 
This bundle is still in development. Use at own risk 🤞🏻

![image](https://github.com/user-attachments/assets/fbd68da1-710d-436c-bee2-9f83a7a8ca32)

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
    resource: '@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml'
```
Don't forget fo add the index to your sulu_search.yaml:

add "abbreviations_published"!

"abbreviations_published" is the index of published, "abbreviations" the index of unpublished elements. Both indexes are searchable in admin.
```yaml
sulu_search:
    website:
        indexes:
            - abbreviations_published
            - ...
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
This bundle contains settings for controlling the following tasks:
- Settings for single view - Toggle for header, default hero snippet and breadcrumbs
- Intermediate pages for breadcrumbs: this can be used to configure the intermediate pages for the breadcrumbs

## 👩‍🍳 Contributing
For the sake of simplicity this extension was kept small.
Please feel comfortable submitting issues or pull requests. As always I'd be glad to get your feedback to improve the extension :).

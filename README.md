# Not Stable

# Laragen

Laragen is a API/Scaffold laravel generator package. It wraps the [laravel-generator](https://github.com/infyomlabs/laravel-generator/)  to add full schema generation and relationships support.

# Features:
- compiles all schema files
- create foreign key constraints for relationships
- creates inverse relationships
- creates pivot tables deduction 
- detects errors in schema relationships
- wildcards for auto naming

----------
#Installation:

 1.  Add composer dependencies:


	    "repositories": [
	        {
	            "type": "vcs",
	            "url": "https://github.com/motia/laragen"
	        },
	        {
	            "type": "vcs",
	            "url": "https://github.com/motia/laravel-generator"
	        }
	    ],
	    "require": {
			...
	        "motia/laragen": "dev-master",
	        "infyomlabs/laravel-generator": "dev-develop"
	    }
 2. Follow the instructions to install [laravel-generator](https://github.com/motia/laravel-generator/)
 3. Add the package service provider
Motia\Generator\MotiaGeneratorServiceProvider::class,

----------


#How To Use:
Store schema files in the `resources/model_schemas` folder.
And run 

    php artisan motia:generate

#Example:
Create the following files:

 1. Product.json

	    [
		    {
		        "fieldInput": "name:string,255",
		        "htmlType": "text",
		        "validations": "",
		        "searchable": false,
		        "fillable": true,
		        "primary": false,
		        "inForm": true,
		        "inIndex": true
		    },
		    {
			    "htmlType": "text",
			    "relation":  "mt1,Category,category_id,id"
		    }
		]
	


 2. Category.json

	    []

 3. Order.json
 
		 [
		    {
		        "fieldInput": "name:string,255",
		        "htmlType": "text",
		        "validations": "",
		        "searchable": false,
		        "fillable": true,
		        "primary": false,
		        "inForm": true,
		        "inIndex": true
		    },
		    {
			    "type": "relation"
		        "relation": "belongsToMany,Article"
		    }
		]
		



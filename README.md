
# Laragen

Laragen is a API/Scaffold laravel generator package. It wraps the [laravel-generator](https://github.com/motia/laravel-generator/)  to add full schema generation and relationships support.

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
	    }
 2. Follow the instructions to install [laravel-generator](https://github.com/motia/laravel-generator/)
 3. Add the package service provider
Motia\Generator\MotiaGeneratorServiceProvider::class,

----------


#How To Use:
Create your schema files in the resources/model_schemas folder.
And run 

    php artisan motia:generate


#Example:
Create the following files:

 1. Article.json

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
		        "relationshipInput": "belongsTo,Category"
		    }
		]
	


 2. Category.json

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
		            "relationshipInput": "hasMany,Article"
		    }
		]

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
		        "relationshipInput": "belongsToMany,Article"
		    }
		]
		


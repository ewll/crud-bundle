# Ewll/CrudBundle

##Installation
```composer require ewll/crud-bundle```

Add to packages configuration:  
config/bundles.php
```
...
Ewll\CrudBundle\EwllCrudBundle::class => ['all' => true]`
...
```
config/routes.yaml
```
ewll_crud:
  resource: '@EwllCrudBundle/Resources/config/routing.yaml'
  prefix: '/crud'
```

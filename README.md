# Ewll/CrudBundle

## Installation
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

## Read Transformers
Use read transformers to transform data into views.  
- You can use our common read transformers from `Ewll\CrudBundle\ReadViewCompiler\Transformer`.
- Or create your:  
In order to create transformer you need to create two classes in `App\Crud\Transformer`.  

1. Implement `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInitializerInterface`. Use abstract class `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInitializerInterface`. This class is needed to define transformer parameters. You will put it into Unit methods `getReadListFields()` and `getReadOneFields()`.
2. Implement `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInterface`. Make it as service with tag `crud_view_transformer`.

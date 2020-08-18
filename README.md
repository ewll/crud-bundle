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
## Units
### Ewll\CrudBundle\Unit\UnitInterface
#### public function setUserProvider(UserProviderInterface $userProvider): void
This function allows to inject mechanism of getting user inside unit.
#### public function getUnitName(): string
The main name for URL. Must be unique of all other utits.
#### public function getEntityClass(): string
The main entity class.
#### public function getSourceClassName(): string
Class name of class implemented Ewll\CrudBundle\Source\SourceInterface allows access to entity storage.
#### public function getAccessRuleClassName(): ?string
Class name of [ewll/user-bundle](https://github.com/ewll/user-bundle) AccessRule class implemented Ewll\UserBundle\AccessRule\AccessRuleInterface.
#### public function getAccessConditions(string $action): array
Must returns array of [Access Conditions](https://github.com/ewll/crud-bundle#access-conditions).
#### public function getCustomActions(): array
Must returns array of [Custom Actions](https://github.com/ewll/crud-bundle#custom-actions) class names. 

### Ewll\CrudBundle\Unit\CreateMethodInterface
#### public function getCreateFormConfig(): FormConfig
Set [Form Config](https://github.com/ewll/crud-bundle#form-config) for entity creation.
#### public function getMutationsOnCreate(object $entity): array
Returns array of fields with new values. This method calls before storing.
#### public function onCreate(object $entity, array $formData): void
Some actions inside transaction within INSERT.
#### public function getCreateExtraData(object $entity): array
Some data you need to see in Response after success creation.

## Access Conditions
@TODO

## Custom Actions
@TODO

## Form Config
Ewll\CrudBundle\Form\FormConfig  
This is some wrapper for [Symfony Form Building](https://symfony.com/doc/current/forms.html#building-forms)

## Read Transformers
Use read transformers to transform data into views.  
- You can use our common read transformers from `Ewll\CrudBundle\ReadViewCompiler\Transformer`.
- Or create your:  
In order to create transformer you need to create two classes in `App\Crud\Transformer`.  

1. Implement `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInitializerInterface`. Use abstract class `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInitializerInterface`. This class is needed to define transformer parameters. You will put it into Unit methods `getReadListFields()` and `getReadOneFields()`.
2. Implement `Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInterface`. Make it as service with tag `crud_view_transformer`.

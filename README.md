# PHP GraphQL OQM
> Forked from [mghoneimy/php-graphql-oqm](https://github.com/mghoneimy/php-graphql-oqm) with typed result objects, mutations, interfaces, variables and more.

This package utilizes the introspection feature of GraphQL APIs to generate a set of classes that map to the structure
of the API schema. The generated classes can then be used in a very simple and intuitive way to query the API server.

Interacting with GraphQL API's using PHP has never been easier!

## New Features in This Fork

- **Result objects**: strongly-typed classes with native return types, hydrated from API responses with `fromArray()`
  or `fromJson()` and converted back with `asArray()`, with date/time fields as Carbon instances
- **Mutations**: a `RootMutationQueryObject` generated from the schema's `mutationType`, built with the same selector
  API as queries
- **Interfaces and unions**: `on{Type}()` inline fragment selectors, PHP interfaces for GraphQL interfaces, and
  hydration into the concrete result class by `__typename`
- **Variables**: declare variables on the root object and send typed input objects as their values
- **Required arguments**: arguments and input fields the schema requires are checked before the request is sent
- **Schema descriptions** as PHPDoc on the generated code, and **operation names** and `__typename` selection

## Installation

The package requires PHP 8.3 or newer, and so does the generated code.

Run the following command to install the package using composer:
```
composer require fo3nix/php-graphql-oqm
```

## Generating The Schema Objects

After installing the package, the first step is to generate the schema objects. This can be easily achieved by executing
the following command:
```
php vendor/bin/generate_schema_objects
```
This script will retrieve the API schema types using the introspection feature in GraphQL, then generate the schema
objects from the types, and save them in the `schema_object` directory in the root directory of the package. You can
override the default write directory by providing the "Custom classes writing dir" value when running the command.

You can also specify all options via command line options:

```
php vendor/bin/generate_schema_objects \
    -u "https://graphql-pokemon.vercel.app/" \
    -h "Authorization" \
    -v "Bearer 123" \
    -d "customClassesWritingDirectory" \
    -n "Vendor\Custom\Namespace" \
    -p "Prefix"
```

or if you prefer long arguments

```
php vendor/bin/generate_schema_objects \
    --url "https://graphql-pokemon.vercel.app/" \
    --authorization-header-name "Authorization" \
    --authorization-header-value "Bearer 123" \
    --directory "customClassesWritingDirectory" \
    --namespace "Vendor\Custom\Namespace" \
    --prefix "Prefix"
```

The optional prefix is prepended to every generated class name, e.g. `PrefixRootQueryObject`, to avoid clashes when
generating classes for several APIs into one namespace.

## Usage

### Building and Running Queries

In all the examples below I'm going to use the super cool public Pokemon GraphQL API as an illustration.

Check out the API at: https://graphql-pokemon.now.sh/

And Github Repo: https://github.com/lucasbento/graphql-pokemon

After generating the schema objects for the public Pokemon API, we can easily query the API by using the
`RootQueryObject`. Here's an example:
```php
$rootObject = new RootQueryObject();
$rootObject
    ->selectPokemons((new RootPokemonsArgumentsObject())->setFirst(5))
        ->selectName()
        ->selectId()
        ->selectFleeRate()
        ->selectAttacks()
            ->selectFast()
                ->selectName();
```
What this query does is that it selects the first 5 pokemons returning their names, ids, flee rates, fast attacks with
their names. Easy right!?

All what remains is that we actually run the query to obtain results:
```php
$results = $client->runQuery($rootObject->getQuery());
``` 
For more on how to use the client class refer to:
- https://github.com/mghoneimy/php-graphql-client#constructing-the-client
- https://github.com/mghoneimy/php-graphql-client#running-queries

(This package uses the [pavel-paliy/php-graphql-client](https://github.com/PavelPaliy/php-graphql-client) fork of that
client, which has the same API.)

### Building and Running Mutations

If the API exposes a `mutationType`, the generator also creates a `RootMutationQueryObject`. It works exactly like the
`RootQueryObject`, but the operation is sent as a `mutation`. Input arguments are set with the generated
`ArgumentsObject` and `InputObject` classes, and the payload fields are selected like any other query object:
```php
$rootMutation = new RootMutationQueryObject();
$payload = $rootMutation->selectProductCreate(
    (new RootMutationProductCreateArgumentsObject())
        ->setInput((new ProductInputInputObject())->setTitle('Hat'))
);
$payload->selectProduct()->selectId()->selectTitle();
$payload->selectUserErrors()->selectMessage();

$results = $client->runQuery($rootMutation->getMutation(), true);
```
This produces:
```
mutation {
productCreate(input: {title: "Hat"}) {
product {
id
title
}
userErrors {
message
}
}
}
```
The returned payload can be hydrated with its result object (e.g. `ProductCreatePayload::fromArray(...)`) just like
query results. Subscriptions are not generated.

### Required Arguments

Arguments and input fields the schema requires (non-null without a default value) are checked before the request is
sent. A field with required arguments makes its arguments object mandatory, so `$rootObject->selectProduct()` without
one fails right away, and building the query with a required argument or input field left unset throws a
`MissingRequiredValueException`:
```php
$rootObject->selectProduct(new RootProductArgumentsObject());
// MissingRequiredValueException: RootProductArgumentsObject: missing required argument "id"
```
An argument bound to a variable with `useVariable()` counts as set.

### Using Variables

Instead of writing argument values into the query, declare variables on the root object with `setVariable()`,
reference them with `useVariable()` on an arguments or input object, and send their values with the query. An input
object's `toArray()` gives the value to send:
```php
$rootMutation = (new RootMutationQueryObject())
    ->setOperationName('CreateProduct')
    ->setVariable('input', 'ProductInput', true);
$rootMutation
    ->selectProductCreate((new RootMutationProductCreateArgumentsObject())->useVariable('input'))
    ->selectProduct()->selectId();

$input = (new ProductInputInputObject())->setTitle('Hat')->setStatus(ProductStatusEnumObject::ACTIVE);
$results = $client->runQuery($rootMutation->getMutation(), true, ['input' => $input->toArray()]);
```
This sends `mutation CreateProduct($input: ProductInput!) { productCreate(input: $input) { product { id } } }` with
the variables `{"input": {"title": "Hat", "status": "ACTIVE"}}`. `useVariable('id', 'productId')` references a
variable with a different name than the argument.

### Operation Names and __typename

Name an operation with `setOperationName()` on the root object, and select the `__typename` meta field of any object
with `selectTypename()`:
```php
$rootObject = (new RootQueryObject())->setOperationName('GetShop');
$rootObject->selectShop()->selectTypename()->selectName();
// query GetShop { shop { __typename name } }
```

### Selecting Interface and Union Fields

Fields that return an interface (e.g. `node(id:)` returning `Node`) or a union return an `InterfaceObject` or
`UnionObject`. Fields declared on the interface itself are selected directly, fields of a specific type are selected
through its `on{Type}()` method, which adds an inline fragment. `__typename` is always selected, so you can tell which
type was returned:
```php
$rootObject = new RootQueryObject();
$node = $rootObject->selectNode((new RootNodeArgumentsObject())->setId('gid://shopify/Product/1'));
$node->selectId();
$node->onProduct()->selectTitle();
$node->onCollection()->selectHandle();
```
This produces:
```
query {
node(id: "gid://shopify/Product/1") {
__typename
id
... on Product {
title
}
... on Collection {
handle
}
}
}
```

### Using Result Objects (New Feature)

The new Result Object functionality allows you to convert raw GraphQL query results into strongly-typed PHP objects:

```php
// Step 1: Build the query (same as before)
$queryRoot = new RootQueryObject();
$pokemonQuery = $queryRoot
    ->selectPokemons((new RootPokemonsArgumentsObject())->setFirst(5))
    ->selectName()
    ->selectId()
    ->selectFleeRate();

// Step 2: Run the query and get results as array
$results = $client->runQuery($queryRoot->getQuery(), true); // true = get as array
$pokemonsData = $results->getData()['pokemons'];

// Step 3: Hydrate the raw array into Result Objects
$pokemons = array_map(function ($pokemonData) {
    return Pokemon::fromArray($pokemonData);
}, $pokemonsData);

// Step 4: Use the type-hinted objects!
foreach ($pokemons as $pokemon) {
    echo "Name: " . $pokemon->getName() . "\n";
    echo "ID: " . $pokemon->getId() . "\n";
    echo "Flee Rate: " . $pokemon->getFleeRate() . "\n";
    
    // Convert back to array if needed
    $pokemonArray = $pokemon->asArray();
}

// You can also hydrate from JSON
$pokemon = Pokemon::fromJson('{"name":"Pikachu","id":"025","fleeRate":0.1}');
```

Benefits of using Result Objects:
- Full IDE autocompletion for all properties
- Type safety with proper return type hints
- Automatic handling of date/time fields with Carbon
- Recursive handling of nested objects
- Easy conversion between objects and arrays/JSON

#### Interface and union results

Interface and union fields are hydrated into the result class of the type that was actually returned, picked by its
`__typename` (which the `InterfaceObject` and `UnionObject` always select). Every GraphQL interface also becomes a PHP
interface that the result classes of its implementations implement, and an interface implementing another interface
extends it:
```php
$shop = Shop::fromArray($results->getData()['shop']);
$featured = $shop->getFeatured(); // Typed as Node
if ($featured instanceof Product) {
    echo $featured->getTitle();
}
$pinned = $shop->getPinned(); // A union value: Product|Article

// Hydrate an interface or union value directly
$node = NodeHydrator::fromArray($results->getData()['node']);
```
`asArray()` keeps the `__typename` of interface and union values, so the array can be hydrated again. Hydrating data
without a `__typename`, or with a type unknown to the generated classes, throws an `InvalidArgumentException`.

## Notes

A couple of notes about schema objects to make your life easier when using the generating classes:

### Dealing With Object Selectors

Whilst scalar field selectors return an instance of the current query object, object field selectors return objects of
the nested query object. This means that setting the `$rootObject` reference to the result returned by an object
selector means that the root query object reference is gone.

Don't:
```php
$rootObject = (new RootQueryObject())->selectAttacks()->selectSpecial()->selectName();
```
This way you end up with reference to the `PokemonAttackQueryObject`, and the reference to the `RootQueryObject` is gone.

Do:
```php
$rootObject = new RootQueryObject();
$rootObject->selectAttacks()->selectSpecial()->selectName();
```
This way you can keep track of the `RootQueryObject` reference and develop your query safely.

### Dealing With Multiple Object Selectors

Suppose we want to get the pokemon "Charmander", retrieve his evolutions, evolution requirements, and evolution
requirements of his evolutions, how can we do that?

We can't do this:
```php
$rootObject = new RootQueryObject();
$rootObject->selectPokemon(
    (new RootPokemonArgumentsObject())->setName('charmander')
)
    ->selectEvolutions()
        ->selectName()
        ->selectNumber()
        ->selectEvolutionRequirements()
            ->selectName()
            ->selectAmount()
    ->selectEvolutionRequirements()
        ->selectName()
        ->selectAmount();
```
This is because the reference is now pointing to the evolution requirements of the evolutions of charmander and not
charmander himself.

The best way to do this is by structuring the query like this:
```php
$rootObject = new RootQueryObject();
$charmander = $rootObject->selectPokemon(
    (new RootPokemonArgumentsObject())->setName('charmander')
);
$charmander->selectEvolutions()
    ->selectName()
    ->selectNumber()
    ->selectEvolutionRequirements()
        ->selectName()
        ->selectAmount();
$charmander->selectEvolutionRequirements()
    ->selectName()
    ->selectAmount();
```
This way we have kept the reference to charmander safe and constructed our query in an intuitive way.

Generally, whenever there's a branch off (just like in the case of getting evolutions and evolution requirements of the
same object) the best way to do it is to structure the query like a tree, where the root of the tree becomes the
reference to the object being branch off from. In this case, charmander is the root and evolutions and evolution
requirements are 2 sub-trees branched off it.

### Improving Query Objects Readability

A couple of hints on how to keep your query objects more readable:
1. Store nodes that will be used as roots in branch offs in meaningful variables, just like the case with charmander.
2. Write each selector on a separate line.
3. Every time you use an object selector, add an extra indentation to the next selectors.
4. Move construction of a new object in the middle of a query (such as an ArgumentsObject construction) to a new line.

## Schema Objects Generation

After running the generation script, the SchemaInspector will run queries on the GraphQL server to retrieve the API
schema. After that, the SchemaClassGenerator will traverse the schema from the root queryType (and mutationType, when
present) recursively, creating a class for every object in the schema spec.

The SchemaClassGenerator will generate a different schema object depending on the type of object being scanned using the
following mapping from GraphQL types to SchemaObject types:
- OBJECT: `QueryObject`
- INPUT_OBJECT: `InputObject`
- ENUM: `EnumObject`
- INTERFACE: `InterfaceObject`
- UNION: `UnionObject`

The descriptions in the schema are added as PHPDoc to the generated classes, selectors, setters, getters and enum
constants, so they show up in your IDE.

Additionally, an `ArgumentsObject` will be generated for the arguments on each field in every object. The arguments
object naming convention is:

`{CURRENT_OBJECT}{FIELD_NAME}ArgumentsObject`

### The QueryObject

The object generator will start traversing the schema from the root `queryType`, creating a class for each query object
it encounters according to the following rules:
- The `RootQueryObject` is generated for the type corresponding to the `queryType` in the schema declaration, this
  object is the start of all GraphQL queries.
- The `RootMutationQueryObject` is generated for the `mutationType`, if the schema declares one. It extends
  `MutationObject`, which sends the selection set as a mutation and adds a `getMutation()` method.
- For a query object of name {OBJECT_NAME}, a class with name `{OBJECT_NAME}QueryObject` will be created.
- For each selection field in the selection set of the query object, a corresponding selector method will be created,
  according to the following rules:
  - Scalar fields will have a simple selector created for them, which will add the field name to the selection set.
    The simple selector will return a reference to the query object being created (this).
  - Object fields will have an object selector created for them, which will create a new query object internally and
    nest it inside the current query. The object selector will return instance of the new query object created.
  - Selectors and setters declare native return types: `static` for scalar selectors and setters, the nested query
    object's class for object selectors, so chained calls are typed.
  - A field with required arguments takes a mandatory arguments object, otherwise it is optional.
- For every list of arguments tied to an object field an `ArgumentsObject` will be created with a setter corresponding
  to every argument value according to the following rules:
  - Scalar arguments: will have a simple setter created for them to set the scalar argument value.
  - List arguments: will have a list setter created for them to set the argument value with an `array`
  - Enum arguments and lists of enums: will have a setter that writes the values unquoted, as GraphQL enum literals
  - Input object arguments: will have an input object setter created for them to set the argument value with an object
    of type `InputObject`
  - Required arguments are listed in the `REQUIRED` constant and checked when the query is built
  - `useVariable()` binds an argument to a variable of the operation

### The InputObject

For every input object the object generator encounters while traversing the schema, it will create a corresponding class
according to the following rules:
- For an input object of name {OBJECT_NAME}, a class with name `{OBJECT_NAME}InputObject` will be created
- For each field in the input object declaration, a setter will be created according to the following rules:
  - Scalar fields: will have a simple setter created for them to set the scalar value.
  - List fields: will have a list setter created for them to set the value with an `array`
  - Enum fields and lists of enums: will have a setter that writes the values unquoted, as GraphQL enum literals
  - Input object arguments: will have an input object setter created for them to set the value with an object of type
    `InputObject`
  - Required fields are listed in the `REQUIRED` constant and checked when the input is serialized
- Fields left unset (`null`) are omitted, while `false`, `0`, `""` and `[]` are sent
- `useVariable()` binds a field to a variable of the operation, and `toArray()` converts the input to the value to send
  as a variable

### The InterfaceObject

For an interface of name {INTERFACE_NAME}, a class with name `{INTERFACE_NAME}InterfaceObject` is created. It has a
selector for every field declared on the interface (following the same rules as the `QueryObject`), and an
`on{TYPE_NAME}()` method for every type implementing the interface, which returns that type's `QueryObject` wrapped in
an inline fragment.

### The UnionObject

For a union of name {UNION_NAME}, a class with name `{UNION_NAME}UnionObject` is created, with an `on{TYPE_NAME}()`
method for every member type, which returns that type's `QueryObject` wrapped in an inline fragment.

### The EnumObject

For every enum the object generator encounters while traversing the schema, it will create a corresponding ENUM class
according to the following rules:
- For an enum object of name {OBJECT_NAME}, a class with name `{OBJECT_NAME}EnumObject` will be created
- For each EnumValue in the ENUM declaration, a const will be created to hold its value in the class, e.g.
  `ProductStatusEnumObject::ACTIVE`. Pass these values to the setters of arguments and input objects

### The ResultObject (New Feature)

For every object in the schema, a corresponding Result Object class will be generated with:
- Properties for each field in the object
- Getter methods for each property with native return types. Every getter is nullable, because fields that were not
  selected in the query are `null`. Lists return `?array` (the element type is in the PHPDoc), enum values `?string`,
  union fields a native union such as `Product|Article|null`, and custom scalars other than date/times `mixed`, since
  they can be serialized as anything
- `fromArray()` method to hydrate the object from an array
- `fromJson()` method to hydrate the object from a JSON string
- `asArray()` method to convert the object back to an array
- Special handling for date/time fields using Carbon
- Recursive handling of nested objects
- `implements` declarations for the PHP interfaces of the GraphQL interfaces it implements

For every GraphQL interface, a PHP interface with the same name declares the getters of the interface's fields, and
extends the PHP interfaces of the interfaces it implements. For every interface and union, a `{TYPE_NAME}Hydrator` class
is generated with:
- `fromArray()` to hydrate the result class matching the `__typename` of the data
- `toArray()` to convert a hydrated object back to an array, including its `__typename`

## License

MIT

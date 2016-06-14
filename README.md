## Synopsis

Creates and builds a vse using admin credentials.

## Build status

* master [![CircleCI](https://circleci.com/gh/ErnestIO/vse-creator-service/tree/master.svg?style=svg)](https://circleci.com/gh/ErnestIO/vse-creator-service/tree/master)
* develop [![CircleCI](https://circleci.com/gh/ErnestIO/vse-creator-service/tree/develop.svg?style=svg)](https://circleci.com/gh/ErnestIO/vse-creator-service/tree/develop)

## Example

To call the service:

```
curl -H "Content-Type: application/json" -X POST -d @examples/simple-router.json --user user@organisation:password http://localhost:8080/router -v -s
```

# Installation

Run composer to build all of the php dependencies
```
$ cd /var/www/vse-creator-service
$ ./composer.phar update
```

## Running Tests

```
make test
```

## Contributing

Please read through our
[contributing guidelines](CONTRIBUTING.md).
Included are directions for opening issues, coding standards, and notes on
development.

Moreover, if your pull request contains patches or features, you must include
relevant unit tests.

## Versioning

For transparency into our release cycle and in striving to maintain backward
compatibility, this project is maintained under [the Semantic Versioning guidelines](http://semver.org/).

## Copyright and License

Code and documentation copyright since 2015 r3labs.io authors.

Code released under
[the Mozilla Public License Version 2.0](LICENSE).


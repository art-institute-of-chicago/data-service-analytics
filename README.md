![Art Institute of Chicago](https://raw.githubusercontent.com/Art-Institute-of-Chicago/template/master/aic-logo.gif)

# Google Analytics Data Service
> A cloneable repo containing examples and architecture for new dataservices

This project gathers data such as pageviews from our Google Analytics to inform popularity metrics for artworks and other resources.



## Features

This project provides the following endpoints.

* `/v1/artworks` - Get a list of all artworks, sorted by the date they were last updated in descending order. Includes pagination options.
* `/v1/artworks/X` - Get a single artwork



## Overview

This API is part of a larger project at the Art Institute of Chicago to build a data hub for all of
our published data—a single place that our forthcoming website and future products can access all the
data they might be interested in in a simple, normalized, RESTful way. This project provides an
API in front of our institutional image archive that will feed into the data hub.



## Requirements

In our development, we've used the following software:

* PHP 7.1 (may work in earlier versions but hasn't been tested)
* MySQL 5.7 (may work in earlier versions but hasn't been tested)
* [Composer](https://getcomposer.org/)

## Installing

To get started with this project, use the following commands:

```shell
# Clone the repo to your computer
git clone https://github.com/art-institute-of-chicago/data-service-analytics.git

# Enter the folder that was created by the clone
cd data-service-analytics

# Install PHP dependencies
composer install
```

## Contributing

We encourage your contributions. Please fork this repository and make your changes in a separate branch. To better understand how we organize our code, please review our [version control guidelines](https://docs.google.com/document/d/1B-27HBUc6LDYHwvxp3ILUcPTo67VFIGwo5Hiq4J9Jjw).

```bash
# Clone the repo to your computer
git clone git@github.com:your-github-account/data-service-analytics.git

# Enter the folder that was created by the clone
cd data-service-analytics

# Install PHP dependencies
composer install

# Start a feature branch
git checkout -b feature/good-short-description

# ... make some changes, commit your code

# Push your branch to GitHub
git push origin feature/good-short-description
```

Then on github.com, create a Pull Request to merge your changes into our
`develop` branch.

This project is released with a Contributor Code of Conduct. By participating in
this project you agree to abide by its [terms](CODE_OF_CONDUCT.md).

We welcome bug reports and questions under GitHub's [Issues](issues). For other concerns, you can reach our engineering team at [engineering@artic.edu](mailto:engineering@artic.edu)

## Licensing

This project is licensed under the [GNU Affero General Public License
Version 3](LICENSE).

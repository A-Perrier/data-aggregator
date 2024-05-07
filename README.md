# Installation

Run these commands

`git clone git@github.com:A-Perrier/data-aggregator.git`

`cd data-aggregator`

`composer install`

Change your DB credentials in .env file

Run `php bin/console doctrine:database:create` in order to create database and `php bin/console doctrine:migrations:migrate` to update database to its latest state.

Please run `php bin/console doctrine:fixtures:load` to create these sample users to use all the features.

| email         | password     |
|---------------|--------------|
| admin@test.fr | testflorajet |
| user@test.fr  | testflorajet |

Then, run `symfony serve` and enjoy the app ! :)


## Development

### Authentication

Considering I'll do some of the bonus bulletpoints in the test, I've first built the authentication.
I've started to make the user, with `php bin/console make:user`.

Then, the authentication flow with `php bin/console make:auth`. I kept the base parameters.

Lastly, I created some users into fixtures to make you the way clean. :)

### Aggregation

I considered to options that could match with the needed feature.
The first one was to have a service per API to get data from. The pros of this method would have been to handle specificities
of each API, such as complex authentication flows, specific headers to send to the host and so on. However, the main con
of this approach would have been a lot of code to rewrite, even considering traits, interfaces or parent classes to group
similar behaviours.

As the project requires to fetch data from sources that are simple to reach, I've prefered another option, which is to have
a YAML configuration file (`config/aggregators.yaml`) where we define a mapping that ensure to associate every property from
the data source and those from our entity. It also allows to precise the data type we're facing and url to fetch. Then,
from a unique service we're able to parse the most of the simple use cases. Pros of this approach is it makes easy to add new sources
by adding only a few config lines into the YAML file. But it as also cons, such as making authentication flows impossible to
handle natively, and it groups all the parsing logic at the same place. I thought more appropriate to do it so, as this test
weren't made to be extremely extensible.

### Difficulties

As I've never manipulated XML parsing with PHP, I spent some time learning how to handle CDATA, XML namespacing and PHP classes for XML.
I think the code related to it should be highly upgradable and cover many more cases that provided API to fetch doesn't use.


### What should be upgrade

- Adding a larger XML parsing support
- Handling authentication flows and securizing sensitive informations
- Avoiding insert multiple times the same article in database (something I tried to handle at first with sourceId property)
- Having a command triggered by a CRON to aggregate articles automatically

### Time spent

As an information you asked for, here's the time repartition I needed to make the project.
- Brainstorming, design thinking, prototyping : 3 hours
- Project setup, authentication, fixtures, basic database config : 1 hour
- Aggregation script, YAML config : 3 hours
- REST actions, JS script, search feature, caching : 2 hours
- Bootstrap structure : 40 minutes

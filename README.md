Dogma
=====
Dogma is a simple framework for building rich, localizable web content. It is released under
the Apache License, Version 2.0. That means you can use it for personal or commercial
use with few restrictions. Please see the LICENSE file for details.

Modules
-------
Modules are components that "plug in" to a Dogma-powered site. The site you are building
should also be built using a Dogma module. For example, let's say you're building a simple
site that uses Google Analytics. You will have a module for your site, plus you will install
the "ga" module.

Structure
---------
All Dogma-powered sites share the same structure:

    root (your project's root folder)
    |
    +- conf (configuration files)
    |
    +- modules (modules)
    |   |
    |   +- <module name>
    |   |  |
    |   |  +- includes
    |   |  |
    |   |  +- templates
    |   |  |
    |   |  +- <language>
    |   |  |  |
    |   |  |  +- <country>
    |   |  |
    |   |  ...
    |   |
    |   ...
    |
    +- public (images, css, and public-facing scripts)
    |
    +- templates (PHP templates, localized Message classes)
       |
       +- <language>
       |  |
       |  +- <country>
       |
       ...

The `includes` directory contains any PHP files or classes that are used to run the module (this
is the "controller" part of the application).
The `templates` directory contains PHP files that only worry about rendering data (this is
the "view" part of the application). Files directly within any `templates` directory are the
default file for situations in which there is no language- or country-specific file or when
the language is unknown.

Requests
--------
Requests from the browser are /always/ processed by a script in the `public` folder. If you
don't want a file to be visible to a user, don't put it in `public`!

A typical request flow involves a PHP script in the `public` folder processing the request
parameters (query strings and form posts), using various module objects and functions,
and populating a model that is used by a template in the `templates` folder to output the
result to the user.

Templates
---------
Templates should only be concerned with displaying data to the user and providing a way
for the user to interact with the data. The interactions should be handled by scripts
in the `public` folder. Templates are rendered via the `Dogma::render()` method. Templates
can use the `Dogma::render()` method to render additional templates (e.g., in a loop or to
simplify the structure of youre pages).

Example
-------
Consider a simple application that adds two numbers. You need a script in `public` that
renders a /template/ to show a form that requests two numbers. Then you need a script
(perhaps the same one) that adds the numbers and displays the result.

/public/index.php (Controller)

    <?php
    require_once('Dogma.php');
    require_once('conf.php');
    
    function add($a, $b) {
      return $a + $b;
    }
    
    if (isset($_REQUEST['a'])) {
      $result = add($_REQUEST['a'], $_REQUEST[b]);
      Dogma::render("main.php", array("result" => $result));
    } else {
      Dogma::render("form.php");
    }

/templates/main.php (Default view)

    <html>
    <head><title>Addition machine</title></head>
    <body>
      <form action="index.php">
        Enter two numbers to add: <input name="a"> + <input name="b">
        <input type="submit" name="submit" value="Add">
      </form>
    </body>
    </html>

/templates/result.php (View for results)

    <html>
    <head><title>Addition machine - result</title></head>
    <body>
      The sum is <?php echo $result ?>
    </body>
    </html>

Localization
------------
Localized messages are encoded as methods on objects. Methods can take parameters, such as
numbers, that can be used to customize the rendering of the message. For example, a different
plural form may be used for 0, 1, or more than 1 using a simple PHP if statement. To implement
this across languages, create a `DefaultMessages` class in `/templates`. Then, create a default
language form called `Messages` that contains your default translations. `Messages` should extend
`DefaultMessages`. This way, if you have a string that is not yet localized, the default message
will be displayed to your users. Then, place a `Messages` class in each of your localized
languages and, if appropriate, countries (e.g. en/US versus en/GB or es/ES and es/MX). Dogma
will load the appropriate `Messages` class for the language of the user. The following examples
show how to localize our simple addition application.

/templates/DefaultMessages.php

    <?php
    class DefaultMessages {
      function AdditionPrompt() {
        return "Enter two numbers to add:";
      }
    }

/templates/Messages.php

    <?php
    class Messages extends DefaultMessages {
    }

/templates/es/Messages.php

    <?php
    class Messages extends DefaultMessages {
      function AdditionPrompt() {
        return "Introduzca dos n&uacute;meros para sumar:";
      }
    }

/templates/main.php (Default view)

    <html>
    <head><title>Addition machine</title></head>
    <body>
      <form action="index.php">
        <?php echo Messages::AdditionPrompt() ?> <input name="a"> + <input name="b">
        <input type="submit" name="submit" value="Add">
      </form>
    </body>
    </html>


bamboo
=========================================

Bamboo is a PHP template engine, which has the following characteristics: 
- native PHP as template language; no compile. familiar, well-defined syntax
- using global functions as helpers/escapers; no redundant `$e->h()`/`E::h()`. just `eh($var)`
- facility to _PULL_ template variables from program side


Installation
------------------------------------------

```sh
composer install tyam\bamboo
```


Basic Usage
--------------------------------------------

PHP side
```php
// You must specify template base directory to bamboo engine.
$basedirs = ['/your/template/dirs', '/come/here'];
$engine = new \tyam\bamboo\Engine($basedirs);

// Render template with variables. You get output string.
$variables = ['title' => $this->getTitle(), 'content' => $this->getContent()];
$output = $engine->render('mytpl', $variables);
$respond->setOutput($output);
```

template side: /your/template/dirs/mytpl.php
```php
<?php /* You must escape string explicitly. */ ?>
<?= htmlspecialchars($title) ?> 
<div>
<?= $content ?>
</div>
```

- A filename extension for template file is 'php'.


Including another template
------------------------------------------

subject template: /your/template/dirs/mytpl.php
```php
content comes here...
<?php $renderer->include('common/another'); ?>
your content continues...
```

another template: /your/template/dirs/common/another.php
```php
write anything...
```

- `$renderer` is a special variable from bamboo, which provides you rendering instructions.
- Template files can be grouped by subdirectories. Then a template name becomes 'subdir/template' (slash delimited).


Template inheritance
------------------------------------------

child template: /your/template/dirs/content.php
```php
<?php $renderer->wrap('common/layout') ?>
content comes here...
```

parent template: /your/template/dirs/common/layout.php
```php
<html>
<body>
header...
<div class="main">
<?php $renderer->content(); ?>
</div>
footer...
</body>
</html>
```

- In child template, by `wrap()` you can specify parent template. The rest part becomes a content for the parent.
- In parent template, by `content()` you can outpupt the content from child.
- Template inheritance must be _single_. You cannot specify `wrap()` twice.
- Multi-level inheritance is supported. Note that `content()` outputs a result of _exact_ child template. You must call `content()` every parent template.


Sections (Capturing block for other template)
----------------------------------------------------

child template: /your/template/dirs/content.php
```php
<?php $renderer->wrap('common/layout'); ?>
<?php $renderer->section('title') ?>
<?= htmlspecialchars($title) ?>
<?php $renderer->endsection('title') ?>
content comes here...
```

parent template: /your/template/dirs/common/layout.php
```php
<html>
<head>
<title><?php $renderer->yield('title') ?></title>
</head>
...
```

- You can capture a block of content, called 'section', between `section()` and `endsection()`.
- Section is named. Then, you can specify the name to `yield()` to output the block.
- Passing a section name to `endsection()` is redundant and optional, but useful as sanity check purpose. Bamboo throws an exception when a passed name was insane.
- Note that evaluation order is matter. Bamboo always evaluates child first. So you can `yield()` only sections captured by descendant templates.


Variables passing
------------------------------------------------

```php
<?php $renderer->wrap('parent', ['title' => $title]) ?>
<?php $renderer->include('another', ['title' => $title]) ?>
```

- You can pass template variables when you call another template.
- Note that you must pass template variables for each template. Template variables are not shared.


Variables pulling
--------------------------------------------------

On production sites, a page is filled by dynamic contents. User name, logged in or not, alerts, recommendations, user comments, etc. They are all side contents, not a main content.  
We must fetch them all from Model layer, then bind to template. That code makes our PHP side (controller, responder or anything) dirty.

To improve this situation, bamboo has the variable-pulling facility. With it, templates automatically pull template variables from PHP side. Then, you can separate the code of side contents from that of main content.  
Variable-pulling is actualized by passing a variable provider to bamboo engine.

PHP side
```php
use tyam\bamboo\Engine;
use tyam\bamboo\VariableProvider;

class MyController implements VariableProvider
{
    // This is the method declared abstract in VariableProvider.
    public function provideVariables(string $template): array
    {
        // Here you provide side contents.
        return [
            'user' => $this->getUser(), 
            'alerts' => $this->getAlerts(), 
            'comments' => $this->getComments()
        ];
    }

    public function action()
    {
        // Get domain result in some way.
        $result = $this->callDomain();

        // Pass $this as VariableProvider to bamboo engine.
        $engine = new Engine(self::$basedirs, $this);

        // Just bind domain result to the template.
        $output = $engine->render('my/template', ['result' => $result]);

        // Then output. Thanks to variable-pulling, the code is quite straight forward.
        $this->response->output($output);
    }
}
```

- Before rendering a template, bamboo calls `provideVariables()` with template name. Then, you return variables for the template.
- In above example, template varialbes `user`, `alerts` and `comments` can be used in 'my/template' and its parents and all included templates.
- If variable names collided, explicit ones, which is passed via `$engine->render()`, `$renderer->wrap()` or `$renderer->include()`, hides implicit ones, which is pulled via `provideVariables()`.


Getting section values
----------------------------------------------

As a result of template rendering, other than result output, there exists section values.  
Usually section values are consumed in templates, and PHP side has no interest in them.  
But, when PHP side is requiring something other than body output, sections are good place to track.  
You can pass `ArrayAccess` object to `$engine->render()`. Then that becomes a collection object to hold all section values.

The good example is e-mail tamplating.

template: e-mail template
```php
<?php /* Store Subject header in a section */ ?>
<?php $renderer->section('Subject') ?>
Confirmation instructions
<?php $renderer->endsection() ?>

<?php /* Store To header in a section */ ?>
<?php $renderer->section('To') ?>
<?= $user->getEmail() ?>
<?php $renderer->endsection() ?>

Thanks for signing up!
To get started, click the link below and confirm your account.
<?= $confirmationUrl ?>
```

PHP side
```php
$variables = ['user' => $user, 'confirmationUrl' => $confirmationUrl];

// Pass an empty array for sections. 
$sections = new \ArrayObject();
$output = $engine->render('email/signup-confirm', $variables, $sections);

// Now we get Subject header and To header from sections
$mailer->setSubject($sections['Subject']);
$mailer->setTo($sections['To']);

$mailer->setBody($output);
$mailer->send();
```

In the example above, we use sections to track some e-mail header values.


Other topics
-----------------------------------------------

- I recommend you define helper/escaper functions in the global namespace. If that is a bother, you can find some functions in 'tyam\bamboo\functions'. Call `Engine::loadFunctions()` if you like it.


Lisence
-----------------------------------------------

MIT

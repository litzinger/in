## In:clude
Include a template like you would an embed, like this:

    {exp:in:clude:includes/.header default_description="{home_meta_description}" default_keywords="{home_meta_keywords}"}

The parameters are passed to the included template as embed parameters, and are available to it like this: {embed:default_description} {embed:home_meta_keywords}

## In:sert
Or, if you would rather insert the template as an early parsed global variable, you can simply do this:

    {in:sert:blog/.blog_entries}

{in:sert} 1.4 supports embed variables just like {exp:in:clude} , but it comes at a slight performance cost, so use wisely and test thoroughly.

If they both support embed parameters, what is the difference? Using {in:sert}, since they are early parsed global variables, will let you take full advantage of the template contents in which it is inserted. For example, if inserted into a blog entry, all of the entry's custom fields are available within the {in:sert} without having to pass them as embed variables. Because its an early parsed global variable you can't use {in:sert} to put an entries tag inside of another entries tag. In such a scenario you would want to use {exp:in:clude} instead.

Using {in:sert} with embeds can let you do some tricky things, such as prefixing custom fields:

    {in:sert:blog/.detail prefix="blog_"}

Then in the `blog/.detail` template:

    {{embed:prefix}grid_field}
        Now this is a reusable grid template, with much less overhead than using a traditional {embed=""} or {exp:in:clude}
    {/{embed:prefix}grid_field}

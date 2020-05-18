Allows live preview and incremental builds for your Gatsby site built with your
Drupal content.

Live Preview will work with JSON:API (using gatsby-source-drupal) or the GraphQL
module (using gatsby-source-graphql). Incremental builds currenly only works
with JSON:API, however, you can still configure the module to trigger a full
build if you are using GraphQL.

# Installation

1. Download and install the module as you would any other Drupal 8 module.
Using composer is the preferred method.
2. Make sure to turn on the Gatsby Refresh endpoint by enabling the environment
variable 'ENABLE_GATSBY_REFRESH_ENDPOINT' in your Gatsby environment file as
documented in https://www.gatsbyjs.org/docs/environment-variables/
3. It's easiest to use this by signing up for Gatsby Cloud at
https://www.gatsbyjs.com/. There is a free tier that will work for most
development and small production sites. This is needed for incremental builds
to work.
4. You can also configure this to run against a local Gatsby development
server for live preview. You need to make sure your Drupal site can communicate
with your Gatsby development server over port 8000. Using a tool such as ngrok
can be helpful for testing this locally.
5. Install the gatsby-source-drupal plugin on your Gatsby site if using JSON:API
or gatsby-source-graphql if you are using the Drupal GraphQL module. There are
no additional configuration options needed for the plugin to work (besides
enabling the __refresh endpoing as documented above). However, you
can add the optional secret key (JSON:API only) to match your Drupal
configuration's secret key.
```
module.exports = {
  plugins: [
    {
      resolve: `gatsby-source-drupal`,
      options: {
        baseUrl: `...`,
        secret: `any-value-here`
      }
    }
  ]
};
```
6. Enable the Gatsby module. If you are using JSON:API it's recomended to
enable the JSON:API Instant Preview module for a faster preview experience and
incremental builds support.
6. Navigate to the configuration page for the Gatsby Drupal module.
7. Copy the URL to your Gatsby preview server (either Gatsby cloud or a locally
running instance). Once you have that, the Gatsby site is set up to receive
updates.
8. Add an optional secret key to match the configuration added to your
gatsby-config.js file as documented above.
9. Optionally add the callback webhook from Gatsby Cloud to trigger your
incremental builds (JSON:API only). You can also check the box which will only
trigger incremental builds when you publish content. You can also enter a
build callback URL in this box (which can be used to trigger build services
such as Netlify).
10. If you are updating the Gatsby Drupal module and are still using an old
version of gatsby-source-drupal, you can select to use the legacy callback.
Note: this will eventually be removed and it's recommended to upgrade your
gatsby-source-drupal plugin on your Gatsby site.
11. Select the entity types that should be sent to the Gatsby Preview server.
At minimum you typically will need to check `Content` but may need other entity
types such as Files, Media, Paragraphs, etc.
12. Save the configuration form.
13. If you want to enable the Gatsby Preview button or Preview Iframe, go to the
Content Type edit page and check the boxes for the features you would like to
enable for that specific content type.

Now you're all set up to use preview and incremental builds! Make a change to
your content, press save, and watch as your Gatsby Preview site magically
updates and your incremental builds are triggered in Gatsby Cloud!

# Menus

To enable Gatsby menus you will need to install the Gatsby JSON:API Extras
module until the point that the issue in JSON:API Extras is resolved
https://www.drupal.org/project/jsonapi_extras/issues/2982133.
The menu functionality relies on the overriding the menu_link_content parent
field to use our "Alias link" formatter.

To expose the menu_link_content endpoint in the JSON:API you will need
the Gatsby user to have the "Administer menus and menu items" permission.
This can be done using basic_auth with the gatsby-source-drupal plugin
or by using the key_auth module with Gatsby to an account with that permission.

# Known Issues

- If you enable the Iframe preview on a content type it may cause an issue with
BigPipe loading certain parts of your page. For now you can get around this
issue by disabling BigPipe or not using the Preview Iframe.

# Future Roadmap

There are a few features that are in the roadmap. Some of them include:
- Better support for Drupal's GraphQL module (this is waiting on future
improvements to gatsby-source-graphql)
- Better integration with Drupal's content moderation system to allow more
flexible content editing workflows and preview experiences.
- Near Keystroke preview (the ability to see content changes as you type)

# Support

The best way to get support is to join the #Gatsby channel in Drupal Slack. You
can also use the issue queue on the project page.

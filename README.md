# Sitegeist.Slipstream
## Header requirements for presentational fusion

"Quantum slipstream transcends the normal warp barrier by penetrating the quantum barrier with a focused quantum field."

The slipstream package allows to define header requirements with presentational fusion by labeling the required tags with a special attribute. They are later deduplicated and moved to the target position. This allows to define additional the JS and CSS requirements directly with the presentational fusion components. 

### Authors & Sponsors

* Martin Ficzel - ficzel@sitegeist.de

*The development and the public-releases of this package is generously sponsored
by our employer http://www.sitegeist.de.*

## Usage

You can mark any html fragment to be moved to the head of the document by 
adding a `data-slipstream` attribute.

```html
    <script data-slipstream href="yourCustomSCript.js" />
    <div>your component</div>
```

The slipstream middleware will parse the full page and detect all those tags. The tags are then removed from the original
location and are appended to the header. Every tag is added only once so if multiple Components require the same JS only one 
is added to the header.

By defining the `data-slipstream` attribute with an xpath the target can be altered. 

```html
    <script data-slipstream="//body" href="yourCustomSCript.js" />
    <div>your component</div>
```

When the setting `Sitegeist.Slipstream.debugMode` is enabled html comments are rendered to mark where tags were removed
and inserted. This is enabled in Development Context by default.

## Inner working and performance

The slipstream http middleware will modify all responses with active `X-Slipstream: Enabled` http header.
This header is added to Neos.Neos:Page and Sitegeist.Monocle:Preview.Page already so this will work for
neos and monocle right away. For other controllers you will have to add the `X-Slipstream: Enabled` manually.

Since the response body is parsed and modified this adds a small performance penalty to every reqest. However
the package is designed to work together with Flowpack.FullpageCache which will im turn cache the whole result 
and mitigate the small performance drawback. 

## Installation

Sitegeist.Slipstream is available via packagist run `composer require sitegeist/slipstream`.

We use semantic-versioning so every breaking change will increase the major-version number.

## Contribution

We will gladly accept contributions. Please send us pull requests.

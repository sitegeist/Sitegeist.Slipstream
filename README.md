# Sitegeist.Slipstream

## Header requirements for presentational fusion

"Quantum slipstream transcends the normal warp barrier by penetrating the quantum barrier with a focused quantum field."

The slipstream package allows defining header requirements with presentational fusion by labeling the required tags with a unique attribute. They are later deduplicated and moved to the target position. This allows defining additional the JS and CSS requirements directly with the presentational fusion components. 

### Authors & Sponsors

* Martin Ficzel - ficzel@sitegeist.de

*The development and the public releases of this package is generously sponsored
by our employer http://www.sitegeist.de.*

## Usage

You can mark any HTML fragment to be moved to the head of the document by 
adding a `data-slipstream` attribute.

```html
    <script data-slipstream src="yourCustomScript.js"></script>
    <div>your component</div>
```

The slipstream middleware will parse the entire page and detect all those tags. The tags are then removed from the original
location and are appended to the header. Every tag is added only once, so if multiple components require the same JS, only one 
is added to the given location.

By defining the `data-slipstream` attribute with an XPath, the target can be altered. 

```html
    <script data-slipstream="//body" src="yourCustomScript.js"></script>
    <div>your component</div>
```

To prepend the tag to the given target, you can add the `data-slipstream-prepend` attribute:

```html
    <script data-slipstream="//body" data-slipstream-prepend src="yourCustomScriptAfterOpenendBody.js"></script>
    <script data-slipstream data-slipstream-prepend src="yourCustomScriptAfterOpenendHead.js"></script>
```

When the setting `Sitegeist.Slipstream.debugMode` is enabled, HTML comments are rendered to mark where tags were removed
and inserted. This is enabled in Development Context by default.  
If the setting `Sitegeist.Slipstream.removeSlipstreamAttributes` is enabled, the attributes from slipstream get removed. 
This is disabled in Development Context by default.

### Using CSS selector id as target

If you want to target a specific element with an id you can also use a CSS target selector.
Be aware, no other selectors types are implemented.

 ```html
<dialog data-slipstream="#target"><p>Greetings, one and all!</p></dialog>
 ```

## Inner working and performance

The slipstream HTTP middleware will modify all responses with an active `X-Slipstream: Enabled` HTTP header.
This header is added to Neos.Neos:Page and Sitegeist.Monocle:Preview.Page already, so this will work for
Neos and Monocle right away. You will have to add the header `X-Slipstream: Enabled` for other controllers.

Since the response body is parsed and modified, this adds a minor performance penalty to every request. However
the package is designed to work together with Flowpack.FullpageCache, which will in turn cache the whole result 
and mitigate the slight performance drawback. 

## Installation

Sitegeist.Slipstream is available via packagist run `composer require sitegeist/slipstream`.

We use semantic-versioning so that every breaking change will increase the major version number.

## Contribution

We will gladly accept contributions. Please send us pull requests.

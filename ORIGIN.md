# Origin

This package was extracted from the Oakland Laravel project after building a reusable certificate template designer, renderer, issuer, verifier, and download workflow there.

Project-specific concerns such as dashboard menu placement, role permissions, school-specific tenancy rules, and host application styling should remain inside each Laravel application.

## Living Test Application

Use the Oakland Laravel project as the living integration test application for package updates.

- Name: Oakland
- Package source folder to edit when updating: `/Applications/MAMP/htdocs/oakland/packages/david-oghi/certificate-generation`
- Local path: `/Applications/MAMP/htdocs/oakland`
- Repository: `https://github.com/davsong01/oakland.git`

Before tagging or pushing a package release, install or update the package inside Oakland and confirm the designer, template storage, issuing flow, verification page, downloads, migrations, routes, middleware, and menu links still work correctly.

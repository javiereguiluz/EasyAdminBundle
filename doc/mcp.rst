MCP Server
==========

.. caution::

    The MCP server is an experimental feature. Its configuration and its
    PHP API can change in any EasyAdmin minor version.

The `Model Context Protocol`_ (MCP) lets AI clients such as ChatGPT,
Claude.ai, Claude Code and Codex use the data of other applications. EasyAdmin
includes an MCP server that lets the people who use your backend connect the
AI client they already use and ask it about the data of the backend.

The server is **read-only**: AI clients can list and read records, but they
can't create, change or delete them.

People log in with the same user they use in the backend, and the AI client
is bound by the same EasyAdmin permissions as that user. The exact list of
rules that apply (and the ones that don't) is explained in
`What AI Clients Can Access`_.

.. note::

    EasyAdmin doesn't send your data to any service. However, the data that
    an AI client reads from your backend is sent to the company that provides
    that AI client (e.g. OpenAI or Anthropic). Expose only the data that you
    are allowed to share with them.

Requirements
------------

The MCP server needs:

* Symfony 7.3 or higher;
* the `symfony/mcp-bundle`_ package and a PSR-7 implementation such as
  ``nyholm/psr7``;
* an OAuth 2.1 authorization server that issues access tokens with the
  ``mcp:read`` scope for the users of your backend (see
  `Authentication`_);
* optionally, the ``symfony/rate-limiter`` package to limit the number of
  calls of each AI client.

Run this command to install the packages:

.. code-block:: terminal

    $ composer require symfony/mcp-bundle nyholm/psr7 symfony/rate-limiter

Configuration
-------------

Add an MCP server called ``easyadmin`` to your application. The
``registry`` option makes it expose all the MCP tools defined by EasyAdmin:

.. code-block:: yaml

    # config/packages/mcp.yaml
    mcp:
        servers:
            easyadmin:
                instructions: >
                    This server gives read-only access to the data of a
                    backend. It is read-only: it cannot create, change or
                    delete data. Call list_collections first, then
                    describe_collection before listing or reading records.
                http:
                    path: '/admin/mcp'
                    # by default, only "localhost" is allowed; add the host
                    # name used by AI clients to reach your application
                    allowed_hosts: ['admin.example.com']
                registry: ['EasyCorp\Bundle\']

Then, import the routes of the MCP server:

.. code-block:: yaml

    # config/routes/mcp.yaml
    mcp:
        resource: .
        type: mcp

Exposing CRUD Controllers
-------------------------

Nothing is exposed to AI clients unless you say so. First, choose the
dashboard that exposes CRUD controllers to MCP by calling one of these two
methods in its ``configureDashboard()`` method:

* ``exposeSelectedCrudsToMcp()``: only the CRUD controllers that you mark as
  exposed are reachable by AI clients. This is the recommended mode;
* ``exposeAllCrudsToMcp()``: all the CRUD controllers of the dashboard are
  reachable by AI clients, except the ones that you mark as excluded.

::

    // src/Controller/Admin/DashboardController.php
    namespace App\Controller\Admin;

    use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
    use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

    class DashboardController extends AbstractDashboardController
    {
        public function configureDashboard(): Dashboard
        {
            return Dashboard::new()
                // ...
                ->exposeSelectedCrudsToMcp();
        }
    }

Only one dashboard can call these methods. If none of them calls them, the
MCP server exposes nothing. CRUD controllers that are not allowed in that
dashboard (e.g. with the ``allowedControllers`` option of the
``#[AdminDashboard]`` attribute) are never exposed.

Then, mark each CRUD controller with the ``#[ExposeToMcp]`` or
``#[ExcludeFromMcp]`` attributes::

    // src/Controller/Admin/ProductCrudController.php
    namespace App\Controller\Admin;

    use EasyCorp\Bundle\EasyAdminBundle\Attribute\ExposeToMcp;
    use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

    #[ExposeToMcp]
    class ProductCrudController extends AbstractCrudController
    {
        // ...
    }

If the exposure depends on the user or the environment, use the
``exposeToMcp()`` and ``excludeFromMcp()`` methods in the ``configureCrud()``
method instead::

    use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

    public function configureCrud(Crud $crud): Crud
    {
        if (!$this->isGranted('ROLE_SUPPORT')) {
            return $crud->excludeFromMcp();
        }

        return $crud->exposeToMcp();
    }

These are the rules applied to decide if a CRUD controller is exposed:

* the methods replace the attributes entirely (e.g. calling ``exposeToMcp()``
  without arguments in a controller with ``#[ExposeToMcp(readOnly: true)]``
  exposes it without the read-only option);
* if both the dashboard and the CRUD controller call these methods in their
  ``configureCrud()`` methods, the last call (the one of the CRUD controller)
  wins;
* a controller can't use both attributes at the same time;
* marking a controller with the same value as the dashboard default (e.g.
  ``#[ExposeToMcp]`` when using ``exposeAllCrudsToMcp()``) does nothing.

Both the attribute and the method accept these options:

``readOnly``
    Set it to ``true`` to make sure that no MCP tool ever changes the data of
    that CRUD controller, even when the backend allows editing it. When using
    ``exposeAllCrudsToMcp()``, use ``#[ExposeToMcp(readOnly: true)]`` to mark
    a single controller as read-only.

``alias``
    The id of the collection used by AI clients. By default, it's the name of
    the CRUD controller class without the ``CrudController`` suffix, in
    snake case (e.g. ``ProductCrudController`` -> ``product``). Set an alias
    when two exposed controllers have the same default id, or to keep the
    same id when renaming the controller class. Aliases must start with a
    lowercase letter and contain only lowercase letters, numbers and
    underscores.

Authentication
--------------

AI clients like ChatGPT and Claude.ai only support OAuth to connect to
remote MCP servers, so the MCP server must be protected with OAuth 2.1
access tokens. EasyAdmin works as an OAuth *resource server*: it doesn't
issue tokens, it only uses the security token created by your firewall.

.. caution::

    EasyAdmin doesn't include an OAuth authorization server yet. You need
    one that issues access tokens for the users of your backend, with the
    ``mcp:read`` scope and with the URL of the MCP endpoint as audience.

Each MCP tool requires a scope. EasyAdmin reads the scopes from the
``scopes`` attribute of the security token, which is the attribute used by
the tokens of `league/oauth2-server-bundle`_. Tokens without that attribute
can't run any tool. Scopes unknown to EasyAdmin are ignored.

Use a dedicated and stateless firewall for the MCP endpoint, defined before
the firewall of the backend, and require authentication for that path:

.. code-block:: yaml

    # config/packages/security.yaml
    security:
        firewalls:
            mcp:
                pattern: ^/admin/mcp
                stateless: true
                # the authenticator of your OAuth resource server...
            admin:
                # ...

        access_control:
            # without this rule, AI clients don't get the 401 response that
            # makes them start the OAuth flow
            - { path: ^/admin/mcp, roles: IS_AUTHENTICATED_FULLY }

Stateless OAuth authenticators (such as the one of
``league/oauth2-server-bundle``) load the user from the user provider on
every request, so disabled or deleted users are refused on their next call
and permission changes apply immediately. If you use another authenticator,
check that it does the same.

What AI Clients Can Access
--------------------------

The MCP tools don't run the controllers of your backend. Instead, they apply
the following EasyAdmin rules on every call, with the user of the access
token:

* only the exposed CRUD controllers can be used;
* the ``access_control`` rules of your security configuration are applied to
  the backend URL of each action, as if the user browsed it (e.g. a rule that
  requires ``ROLE_ADMIN`` for ``^/admin`` also applies to AI clients);
* the permission of each action (``Actions::setPermission()``) and the
  disabled actions (``EA_EXECUTE_ACTION``);
* the ``#[IsGranted]`` attributes of the CRUD controller class and of its
  ``index()`` and ``detail()`` methods;
* the listeners of the ``BeforeCrudActionEvent``; when a listener sets a
  response, the call is refused;
* the entity permission (``Crud::setEntityPermission()``, ``EA_ACCESS_ENTITY``)
  of every record and of every related record shown with its label;
* the field permissions (``setPermission()`` of fields, ``EA_VIEW_FIELD``)
  and the pages where each field is displayed;
* the query of the ``createIndexQueryBuilder()`` method of the CRUD
  controller. Records loaded by id (e.g. with the ``get_record`` tool) are
  also loaded with that query, so the restrictions applied there (e.g.
  showing only the records of the current customer) also apply to them.

Fields hidden from the user, fields that use ``formatValue()`` and fields
whose values are never sent to AI clients (see `Field Values`_) can't be
used to sort, filter or search records. Otherwise, AI clients could find out
their values by searching or sorting.

When the CRUD controller defines an entity permission, ``list_records``
doesn't return the total number of records, because that number would
include the records that the user can't access.

These parts of your application **don't apply** to AI clients:

* ``kernel.request`` listeners and Doctrine filters that depend on the route
  or on the session of the backend (MCP requests are stateless);
* any code added to your own ``index()`` or ``detail()`` methods when
  overriding them, and the ``AfterCrudActionEvent`` listeners;
* custom actions and batch actions.

Tools
-----

The MCP server provides these tools to AI clients:

``list_collections``
    Lists the exposed CRUD controllers that the user can access, with the
    actions (``index`` and ``detail``) that the user can run on each of them.
    Collections that the user can't access are reported as missing by the
    other tools too.

``describe_collection``
    Returns the fields of a collection (with their `JSON Schema`_), its
    filters and their comparisons, its sortable fields, its default sort
    and its page size. The fields marked as ``x-required`` are required in
    the backend forms, but that's only a hint.

``list_records``
    Lists the records of a collection. It supports searching, the filters
    of the CRUD controller, sorting and pagination.

``get_record``
    Returns all the fields of a record shown in the ``detail`` page.

Field Values
------------

Only the values of these field types are sent to AI clients:

* ``TextField``, ``TextareaField``, ``TextEditorField``, ``EmailField``,
  ``UrlField``, ``TelephoneField``, ``SlugField`` and ``ColorField``;
* ``IdField``, ``IntegerField``, ``NumberField`` and ``PercentField``;
* ``BooleanField``;
* ``DateField``, ``DateTimeField`` and ``TimeField`` (in ISO 8601 format);
* ``ChoiceField`` (the stored value, including enums);
* ``MoneyField`` (an object with the ``amount`` and ``currency`` keys);
* ``CountryField``, ``CurrencyField``, ``LanguageField``, ``LocaleField``
  and ``TimezoneField`` (the stored code);
* ``AssociationField`` and ``CollectionField`` of Doctrine associations.

The values of other fields (e.g. ``ImageField``, ``FileField``,
``CodeEditorField`` and your custom fields) are never sent. The
``describe_collection`` tool lists them as ``unsupported_fields``.

Other rules applied to field values:

* when a field uses the ``formatValue()`` method, AI clients get the
  output of that method (as plain text) instead of the original value.
  This keeps the values masked in the backend (e.g. showing only the last
  digits of a card number) masked for AI clients too;
* related records are returned as their id. Their label is only included
  when the CRUD controller of the related entity is exposed and the user
  can access that record. To-many associations include the total number of
  related records and the first ones;
* long strings are truncated and the ``truncated_fields`` key of the record
  lists them.

Supporting Other Field Types
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Create a service that implements ``McpValueNormalizerInterface`` to send
the values of other field types to AI clients::

    // src/Mcp/RatingValueNormalizer.php
    namespace App\Mcp;

    use App\Admin\Field\RatingField;
    use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
    use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\McpValueNormalizerInterface;

    class RatingValueNormalizer implements McpValueNormalizerInterface
    {
        public function supports(FieldDto $field): bool
        {
            return RatingField::class === $field->getFieldFqcn();
        }

        public function getSchema(FieldDto $field): array
        {
            return ['type' => 'integer', 'minimum' => 1, 'maximum' => 5];
        }

        public function normalize(FieldDto $field, mixed $value): mixed
        {
            return (int) $value;
        }
    }

If you use the default services configuration, the service is
autoconfigured with the ``ea.mcp_value_normalizer`` tag. Otherwise, add
that tag to the service.

Adding Information to Collections
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Bundles that define their own MCP tools can add information to the
results of ``list_collections`` and ``describe_collection`` (for example,
the actions of their tools) with a service that implements
``McpCollectionExtensionInterface``:

* ``getAllowedActions()`` returns the extra actions that the user can run
  on a collection. They are listed as returned, so the service must check
  the permissions of the user first;
* ``extendDescription()`` receives the result of ``describe_collection``
  and returns it with the added information.

If you use the default services configuration, the service is
autoconfigured with the ``ea.mcp_collection_extension`` tag. Otherwise,
add that tag to the service.

Untrusted Data
~~~~~~~~~~~~~~

The values of the records are returned inside an ``untrusted_data`` key,
next to a notice that tells AI clients to treat them as data and never as
instructions. This reduces the risk of prompt injection (e.g. a customer
writing "ignore your previous instructions" in a support ticket), but it
doesn't remove it. Expose to AI clients only the data whose content you
can live with being read as text by an AI model.

Similarly, all MCP tools are annotated as read-only, but AI clients are
free to ignore those annotations. They are a hint and never replace the
checks explained in this article.

Limits
------

Configure the limits of the MCP server in the ``easy_admin`` option:

.. code-block:: yaml

    # config/packages/easy_admin.yaml
    easy_admin:
        mcp:
            limits:
                # the maximum number of records of each page (the page size
                # of the CRUD controller is used when it's smaller)
                max_page_size: 50
                # calls with larger responses fail with an error that asks
                # to narrow the results (responses are never truncated)
                max_response_bytes: 262144
                # longer strings are truncated
                max_string_length: 2000
                # the related records included for each to-many association
                max_to_many_items: 10
                # the calls per minute of each user, for all their AI clients
                # (it requires symfony/rate-limiter; 0 disables it)
                calls_per_minute: 120
                # the maximum execution time of each call, in seconds
                time_limit: 30

The time limit is the PHP execution time of the call. Slow database
queries are better limited in the database itself (e.g. with the
``statement_timeout`` option of PostgreSQL or the ``max_execution_time``
option of MySQL for the database user of the application).

Checking the Configuration
--------------------------

Run the following command to check the configuration of the MCP server:

.. code-block:: terminal

    $ php bin/console easyadmin:mcp:doctor

It checks the installed packages, the dashboard and the exposed CRUD
controllers, the MCP endpoint and its firewall and access control rules,
and the rate limiter.

Services that implement ``McpDoctorCheckInterface`` add their own checks
to this command. Their ``check()`` method yields one array per check,
with the status (one of the ``STATUS_*`` constants of the interface), the
name and the details of the check. If you use the default services
configuration, they are autoconfigured with the ``ea.mcp_doctor_check``
tag. Otherwise, add that tag to the service.

Connecting AI Clients
---------------------

The URL of the MCP server is the URL of your backend followed by the path
of the MCP server (e.g. ``https://admin.example.com/admin/mcp``). It must
be reachable from the internet with HTTPS for ChatGPT and Claude.ai.

ChatGPT
    Enable the developer mode in the settings of ChatGPT, create an app
    with the URL of the MCP server and choose OAuth as the authentication
    method.

Claude.ai
    Add a custom connector with the URL of the MCP server in the Connectors
    section of the settings.

Claude Code
    Run the following command and then run the ``/mcp`` command inside
    Claude Code to log in:

    .. code-block:: terminal

        $ claude mcp add --transport http easyadmin https://admin.example.com/admin/mcp

Codex
    Add the URL of the MCP server to the MCP servers of its configuration
    and log in when Codex asks for it.

In all cases, the AI client opens the login page of your backend and then
asks you to authorize the access.

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
.. _`symfony/mcp-bundle`: https://github.com/symfony/mcp-bundle
.. _`league/oauth2-server-bundle`: https://github.com/thephpleague/oauth2-server-bundle
.. _`JSON Schema`: https://json-schema.org/

Web API is a framework for implementing different types of web services in Totara.

Web API is described by a GraphQL schema, resolvers are implemented
as PHP classes in webapi component namespaces and web service end-points
are expected to use GraphQL persisted queries stored in webapi subdirectories.

Direct GraphQL execution should not be enabled on production servers
for security reasons. Developers may define GRAPHQL_DEVELOPMENT_MODE
constant in config.php when developing new queries or testing persisted operations.
Please note that GraphQL executor requires a valid TotaraSession cookie
from active browser session.


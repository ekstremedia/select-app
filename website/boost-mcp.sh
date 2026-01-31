#!/bin/bash
cd /Users/terjenesthus/projects/select-app/website
exec /opt/homebrew/opt/php@8.4/bin/php -d error_reporting=0 artisan boost:mcp 2>/dev/null

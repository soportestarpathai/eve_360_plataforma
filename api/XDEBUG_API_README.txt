Si las APIs devuelven HTML (errores de Xdebug) en lugar de JSON:

1. Edite el php.ini de WAMP (ej: C:\wamp64\bin\php\php8.x\php.ini)
2. Busque la línea con xdebug.mode
3. Cámbiela a: xdebug.mode=off
4. Reinicie Apache

O comente la extensión Xdebug:
; zend_extension = xdebug

Luego reinicie Apache desde el icono de WAMP.

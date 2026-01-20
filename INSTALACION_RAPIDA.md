# 🚀 Instalación Rápida - Mejoras Implementadas

## Pasos Mínimos para Activar Todas las Mejoras

### 1️⃣ Crear archivo `.env`
```bash
# Copiar plantilla
cp .env.example .env

# Editar con tu token real de Banxico
# BANXICO_TOKEN=tu_token_real_aqui
```

### 2️⃣ Crear directorios necesarios
```bash
mkdir logs cache
chmod 755 logs cache  # Linux/Mac
# En Windows, dar permisos de escritura a IIS_IUSRS
```

### 3️⃣ Aplicar índices SQL (Opcional pero Recomendado)
```sql
-- En MySQL:
source config/database_indexes.sql;

-- O desde línea de comandos:
mysql -u root -p investor < config/database_indexes.sql
```

### 4️⃣ ¡Listo! 🎉
Las mejoras ya están activas. El sistema ahora:
- ✅ Lee el token de Banxico desde `.env`
- ✅ Usa caché para mejorar rendimiento
- ✅ Registra errores en `logs/app.log`
- ✅ Valida y sanitiza todas las respuestas de API
- ✅ Monitorea la disponibilidad de APIs

---

## Verificación Rápida

### Probar que todo funciona:
```bash
# Ver logs (si hay errores aparecerán aquí)
tail -f logs/app.log

# Verificar estado de APIs
curl http://localhost/api/monitor_status.php
```

---

## Configuración Rápida

### Para Desarrollo (más logging):
```env
LOG_LEVEL=DEBUG
CACHE_ENABLED=true
CACHE_DURATION=300
```

### Para Producción (menos logging):
```env
LOG_LEVEL=ERROR
CACHE_ENABLED=true
CACHE_DURATION=1800
```

---

## ¿Problemas?

1. **Error: Class not found**
   - Verifica que los archivos en `config/` estén completos
   - Verifica permisos de lectura

2. **Error: Cannot write to cache/logs**
   - Verifica permisos de escritura en `cache/` y `logs/`
   - En Windows: propiedades de carpeta → Seguridad → Agregar IIS_IUSRS

3. **API de Banxico no funciona**
   - Verifica que el token en `.env` sea correcto
   - Revisa `logs/app.log` para ver el error específico

---

Para más detalles, ver `MEJORAS_IMPLEMENTADAS.md`

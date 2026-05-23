# Biffi Olimpiadas

Plataforma web educativa para las XVIII Olimpiadas de Matematicas Biffi 2026.

## Requisitos

- XAMPP con Apache y MySQL activos.
- PHP incluido en XAMPP.
- Base de datos MySQL llamada `olimpiadas_pro`.

## Instalacion local

1. Copia el proyecto en `C:\xampp\htdocs\biffi-olimpiadas`.
2. Enciende Apache y MySQL desde XAMPP.
3. Importa la base de datos:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\biffi-olimpiadas\biffi_olimpiadas_v3.sql
```

4. Aplica las migraciones SQL incluidas si corresponde.
5. Abre:

```text
http://localhost/biffi-olimpiadas
```

## Usuario de prueba

```text
carlos.nunez / AdminBiffi2026!
```

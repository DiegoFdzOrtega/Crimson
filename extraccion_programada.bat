@echo off
REM ==================================================================
REM  extraccion_programada.bat - Lanza la extraccion automatica (RA3 e)
REM  El Programador de tareas de Windows ejecuta este archivo cada dia.
REM  Requisito: MySQL de XAMPP debe estar encendido.
REM ==================================================================

REM %~dp0 es la carpeta donde esta este .bat (la del proyecto)
if not exist "%~dp0exportaciones" mkdir "%~dp0exportaciones"

REM Ejecuta el script PHP y guarda lo que escribe en un registro (log)
"C:\xampp\php\php.exe" "%~dp0extraccion_automatica.php" >> "%~dp0exportaciones\registro_extracciones.log" 2>&1

# Cláusulas de contrato y aviso de tratamiento de datos

> **Borrador de trabajo, no documento firmable.** Redactado desde el lado
> técnico para que encaje con lo que el sistema realmente registra. Debe
> revisarlo un abogado colombiano antes de usarse. Agosto de 2026.

Dos documentos distintos, con propósitos opuestos:

| | Protege a | Se firma con | Sin él |
|---|---|---|---|
| **A. Cláusulas de contrato** | BryNex | El aliado | Los canarios y las marcas de agua no sirven de prueba de nada |
| **B. Aviso de tratamiento** | El usuario | Cada persona que entra al panel | El registro de accesos que construimos es *tu* incumplimiento |

El segundo no es opcional. Registrar IP, equipo y huella de navegador de una
persona identificada es tratamiento de datos personales bajo la Ley 1581 de
2012. Hacerlo sin autorización te expone ante la SIC — y en una disputa, el
abogado del otro lado lo usaría para invalidar precisamente la prueba que
querías presentar.

---

## A. Cláusulas para el contrato de aliado

### A.1 Titularidad y prohibición de reproducción

> La plataforma BryNex, incluyendo su código fuente y objeto, su arquitectura,
> su modelo de datos, sus interfaces gráficas, sus flujos de trabajo, sus
> reglas de negocio, sus reportes y su documentación, es propiedad exclusiva de
> **[RAZÓN SOCIAL DE BRYNEX, NIT …]** y se encuentra protegida por la Ley 23 de
> 1982, la Ley 1915 de 2018 y la Decisión Andina 351 de 1993.
>
> El presente contrato otorga al ALIADO una licencia de uso **no exclusiva, no
> transferible y revocable**, limitada a la operación de su propio negocio
> durante la vigencia del contrato. No transfiere ningún derecho patrimonial de
> autor.
>
> El ALIADO se obliga a no reproducir, adaptar, traducir, descompilar, someter a
> ingeniería inversa ni derivar obra alguna de la plataforma, ya sea
> directamente o a través de terceros. Esta obligación comprende expresamente el
> **desarrollo de un producto que replique la interfaz, la secuencia de
> operaciones, la estructura de datos o las reglas de negocio de BryNex**, con
> independencia de que se escriba código distinto.

*Por qué esa última frase:* sin ella, la defensa obvia es «yo no copié ni una
línea de su código, lo escribí desde cero». Lo que se protege aquí es la
expresión —pantallas, flujos, estructura—, no solo el texto del código.

### A.2 Cuentas nominales y no cesión de credenciales

> Cada cuenta de acceso corresponde a **una persona natural identificada**. El
> ALIADO se obliga a:
>
> a) Registrar cada usuario con el nombre y documento de identidad reales de la
>    persona que lo usará, absteniéndose de crear cuentas genéricas, compartidas
>    o de función.
> b) No ceder, prestar ni compartir credenciales, ni permitir que una cuenta sea
>    usada por persona distinta de su titular.
> c) Solicitar por escrito la baja de una cuenta dentro de los **cinco (5) días
>    hábiles** siguientes a la desvinculación de su titular.
> d) Informar previamente a BRYNEX cuando pretenda otorgar acceso a personal no
>    vinculado laboralmente a él —contratistas, asesores externos, proveedores de
>    tecnología—, indicando su identidad y el propósito del acceso.
>
> El ALIADO responde por todo acto realizado desde las cuentas de su
> organización como si lo hubiera realizado él mismo.

*Por qué el literal d):* es el que convierte «el aliado le dio un usuario a su
ingeniero» en un incumplimiento concreto y demostrable, en lugar de una
sospecha. Y el último párrafo cierra la salida de «fue un empleado por su
cuenta, yo no sabía».

### A.3 Registro de actividad y auditoría

> El ALIADO reconoce y acepta que BRYNEX registra, por razones de seguridad y
> trazabilidad: fecha y hora de cada acceso, dirección IP, identificador del
> equipo y del navegador utilizados, y las operaciones realizadas sobre la
> información. El ALIADO se obliga a informar de ello a sus usuarios y a obtener
> de cada uno la autorización de tratamiento de datos correspondiente, en los
> términos del Anexo [B].
>
> BRYNEX podrá incorporar en la información y en los archivos exportados
> **elementos de trazabilidad y registros de control** que permitan determinar
> el origen de una extracción. El ALIADO se obliga a no suprimirlos ni alterarlos.

*Por qué está redactado así de vago:* nombrar «marcas de agua en las
propiedades del documento» o «registros canario» sería explicarle al infractor
qué buscar y qué borrar. Esta redacción basta para que su remoción sea
incumplimiento, sin decir dónde están.

### A.4 Cláusula penal

> El incumplimiento de las cláusulas A.1 o A.2 dará lugar a una pena de
> **[VALOR — p. ej. 200 SMMLV]**, exigible sin necesidad de requerimiento
> judicial y **sin perjuicio de la indemnización de los perjuicios que superen
> dicha suma** (artículos 1592 y siguientes del Código Civil), así como de la
> terminación inmediata del contrato y de las acciones penales por violación a
> los derechos patrimoniales de autor (artículo 271 del Código Penal).

*Por qué «sin perjuicio de»:* sin esa frase, la pena pactada se entiende como
tope de la indemnización. Con ella, es un piso.

### A.5 Supervivencia

> Las obligaciones de las cláusulas A.1, A.2 y A.3 subsisten por **cinco (5)
> años** contados desde la terminación del contrato, cualquiera que sea su causa.

---

## B. Aviso de tratamiento de datos para usuarios del panel

Se acepta **una sola vez, en el primer ingreso**, y la aceptación se registra
con fecha, hora e IP. Sin aceptación no debería haber acceso.

### Texto propuesto

> **Tratamiento de tus datos como usuario de BryNex**
>
> **[RAZÓN SOCIAL], NIT [·],** con domicilio en [·], es responsable del
> tratamiento de tus datos personales.
>
> **Qué registramos.** Tu nombre, documento de identidad, teléfono y correo. Y
> cada vez que entras: la fecha y hora, la dirección IP, un identificador del
> equipo desde el que ingresas y las características técnicas de tu navegador.
> También queda registro de las operaciones que realizas dentro del sistema.
>
> **Para qué.** Únicamente para tres cosas: darte acceso, dejar constancia de
> quién hizo cada operación, y detectar accesos no autorizados. **No usamos
> estos datos para evaluar tu desempeño laboral, ni los compartimos con tu
> empleador con ese fin, ni los vendemos o cedemos a terceros.**
>
> **Cuánto tiempo.** Los registros de acceso se conservan **2 años** y el
> registro de operaciones **5 años**. Cumplido el plazo se eliminan
> automáticamente.
>
> **Tus derechos.** Puedes conocer, actualizar y rectificar tus datos; solicitar
> prueba de esta autorización; ser informado sobre el uso que les damos;
> presentar quejas ante la Superintendencia de Industria y Comercio; y revocar
> la autorización o pedir la supresión de tus datos.
>
> Ten en cuenta que los registros de acceso y actividad soportan la seguridad
> del sistema y obligaciones de trazabilidad: **mientras tu cuenta esté activa
> no es posible suprimirlos**, porque sin ellos no podríamos acreditar quién
> realizó cada operación. Si revocas la autorización, procederemos a cerrar tu
> cuenta.
>
> **Cómo ejercerlos.** Escribe a **[correo]**. Respondemos consultas en 10 días
> hábiles y reclamos en 15, conforme a la Ley 1581 de 2012.
>
> ☐ He leído este aviso y **autorizo** el tratamiento de mis datos personales en
> los términos descritos.

*Por qué el párrafo de la supresión:* si prometes borrado incondicional, un
usuario puede exigir que borres justo la evidencia que lo señala. La ley admite
limitar la supresión cuando hay un deber de conservación o un interés legítimo;
lo que no admite es callarlo. Decirlo de frente es lo que lo hace sostenible.

*Por qué la frase sobre desempeño laboral:* es el uso que los trabajadores temen
y el que convertiría una medida de seguridad en un conflicto laboral. Acotarlo
por escrito protege a las dos partes.

---

## C. Qué falta para que esto funcione

1. ~~Pantalla de aceptación~~ **Hecha.** `/tratamiento-datos`, exigida por el
   middleware `ExigirAvisoTratamiento`. Guarda fecha, IP y **versión** del
   aviso: al subir `AvisoTratamientoController::VERSION` se vuelve a pedir a
   todos, sin tener que resetear nada a mano.
2. ~~Definir la retención~~ **Hecho:** 2 años para los accesos (dato de
   vigilancia: el principio de temporalidad de la Ley 1581 pide el mínimo) y 5
   años para la bitácora de operaciones (tiene que sobrevivir a la ventana de
   reclamación, y las obligaciones de la cláusula A.5 duran 5 años tras
   terminar el contrato). Lo aplica `retencion:limpiar`, mensual en el
   scheduler. **Si cambias un plazo, cámbialo en los dos sitios.**
3. **Registro Nacional de Bases de Datos (RNBD)** ante la SIC: verificar con el
   abogado si BryNex supera el umbral de activos que obliga a inscribirse.
4. **Firmar el Anexo B con los aliados existentes**, no solo con los nuevos. Los
   registros de acceso ya se están capturando.

## D. Lo que ningún contrato arregla

Una persona con acceso legítimo ve la interfaz y puede reproducir la idea. Eso
no lo impide ninguna cláusula. Lo que estos documentos hacen es distinto y más
modesto: convertir una sospecha en un hecho demostrable, y darle consecuencia
económica. La defensa real sigue siendo lo que no se ve desde la pantalla —los
datos históricos, las integraciones con Enlace Operativo, RUAF y PILA, y la
velocidad con la que el producto cambia.

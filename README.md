# WooCommerce Store Credit

**WooCommerce Store Credit** es un plugin diseñado para agregar un sistema de créditos y cashback a tu tienda WooCommerce. Permite a los clientes acumular crédito con sus compras y utilizarlo como método de pago en futuros pedidos, fomentando así la fidelidad y las ventas recurrentes.

## Características Principales

*   **Sistema de Cashback Automático**: Los clientes ganan un porcentaje del valor de su compra como crédito en la tienda (configurable).
*   **Gestión de Estados de Crédito**:
    *   **Créditos Pendientes**: Los créditos ganados pueden quedar en estado "pendiente" durante un número configurable de días (por ejemplo, para cubrir el periodo de devoluciones) antes de estar disponibles.
    *   **Expiración de Créditos**: Configura una caducidad automática para los créditos (ej. 365 días).
*   **Pago con Crédito en el Checkout**: Los usuarios pueden aplicar su saldo disponible de forma total o parcial durante el proceso de pago (Checkout).
*   **Reglas y Exclusiones**:
    *   Excluye productos en oferta de la obtención de cashback.
    *   Excluye categorías de productos específicas para que no otorguen cashback ni puedan ser pagadas con crédito.
*   **Mensajes Personalizados en el Frontend**:
    *   Muestra dinámicamente cuánto crédito ganará un cliente al comprar un producto directamente en la página del producto.
    *   Incentiva a los usuarios invitados a registrarse o iniciar sesión para aprovechar el cashback.
*   **Área de "Mi Cuenta"**: Los clientes tienen una pestaña dedicada en "Mi Cuenta" para ver su saldo actual y el historial completo de sus transacciones (libro mayor/ledger).
*   **Gestión de Administrador**:
    *   Los administradores pueden ver y ajustar manualmente el saldo de crédito de cualquier cliente desde su perfil en el panel de WordPress.
    *   Listado de usuarios y sus saldos de crédito.
*   **Integración con Devoluciones (Refunds)**: Gestiona correctamente los saldos de crédito cuando se realizan reembolsos de pedidos en WooCommerce.
*   **Notificaciones por Correo Electrónico**: Avisos automáticos a los clientes cuando se les otorga nuevo crédito o cuando este pasa a estar disponible.

## Requisitos

*   WordPress 5.8 o superior.
*   WooCommerce instalado y activo.
*   PHP 7.4 o superior.

## Instalación

1.  Sube la carpeta `wc-store-credit` al directorio `/wp-content/plugins/` de tu instalación de WordPress.
2.  Activa el plugin a través del menú 'Plugins' en WordPress.
3.  Ve a **WooCommerce > Ajustes > Store Credit** para configurar los parámetros iniciales (porcentaje de cashback, días de pendiente, exclusiones, etc.).

## Configuración

En la pestaña **Store Credit** de los ajustes de WooCommerce, podrás personalizar:

*   **Cashback Percentage**: El porcentaje de la compra que se devuelve como crédito al cliente.
*   **Expiration Days**: Días hasta que el crédito expire (0 para que no expire).
*   **Pending Days**: Días que el crédito permanecerá retenido en estado "Pendiente" antes de poder ser usado.
*   **Exclude Sale Items**: Opción para no dar cashback por productos que ya tienen descuento.
*   **Exclude Categories**: Permite seleccionar categorías en las que no aplicará el sistema de crédito.
*   **Mensajes Personalizables**: Textos que se mostrarán en la ficha de producto y en el checkout (con soporte para variables como `{amount}`, `{login_url}`, etc.).

## Base de Datos

El plugin crea una tabla personalizada en la base de datos (`[prefix]_store_credit_transactions`) para mantener un registro inmutable (Ledger) de todas las transacciones de crédito (ganancias, gastos, ajustes administrativos y expiraciones) asegurando la integridad del saldo de los usuarios.

## Autor

**Daniel Henriquez**
[https://dhenriquez.cl](https://dhenriquez.cl)
<?php 
define('USE_FETCH_FOR_REQUESTS',true);
?><?php 
define('WP_HOME','https://diy-pwa.com/scope:0.6598669343368218');
define('WP_SITEURL','https://diy-pwa.com/scope:0.6598669343368218');
?><?php 
define('WP_DEBUG_LOG',true);
define('WP_DEBUG_DISPLAY',false);
?><?php define( 'CONCATENATE_SCRIPTS', false );
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'database_name_here' );

/** Database username */
define( 'DB_USER', 'username_here' );

/** Database password */
define( 'DB_PASSWORD', 'password_here' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY','!<*QFE#%XZd!R5qS?jaY!-d)V!lQ]4giGIiEd2uU');
define( 'SECURE_AUTH_KEY','Spso.td@SjbJ]#b[Tnt57#h6)%BQJ]>eBJ^x(z).');
define( 'LOGGED_IN_KEY','w*ujM44aE(Xf!Ck)JZI,lJIBUt*58yek8Epbr9cM');
define( 'NONCE_KEY','0XL5!C(jrv(VE0BhW5%O<Os]Dy%HZjJM^rawVyb<');
define( 'AUTH_SALT','ZNVYkY^gYj[82wb]P*6(1Y3-UQg#=sRg_1wOw!MX');
define( 'SECURE_AUTH_SALT','Y!DuPbWJ5L?4kkbS#lCXr.dM9wlvzAg<#oEw/+o1');
define( 'LOGGED_IN_SALT','FnYO]LrEe_sZ09i^K,Fe&_QUTun+$<8oOnuAMo&5');
define( 'NONCE_SALT','UpB.+imx#Gzn_.?WSHMqq%NE00=7=lk>Uf3!_Hug');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG',true);

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

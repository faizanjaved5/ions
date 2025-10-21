<?php


/**
 * The base configuration for WordPress
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 * This file contains the following configurations:
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 * @package WordPress
 */


// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u185424179_WtONJ' );

/** Database username */
define( 'DB_USER', 'u185424179_S4e4w' );

/** Database password */
define( 'DB_PASSWORD', '04JE8wHMrl' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 * @since 2.6.0
 */

 define( 'AUTH_KEY',          'ib 2)Rn1A)xx!eCXy!o~o/Yr<,gLof6Ho?{vt<85AA[A+|H|pM^0(hRZ`ylbW=@+' );
 define( 'SECURE_AUTH_KEY',   '!&(O^Gp_KPAn4LM[;O[h5eZjarIcD$cEy(YWF eCi3q5=est~p.BEm:h!P{%,DTE' );
 define( 'LOGGED_IN_KEY',     'C;3Ik&>YeG-gDRb:_2j5-v=ckk}(;e@?ez:6ef4Min1]W*|eu;+!L5OgU(c E;w[' );
 define( 'NONCE_KEY',         '1-@*a7jL{H9{!@!.l-+fa@ 7|h:QqT}3!zQj)GWp//J/2/(t<S/=S&/R#^r+@g;~' );
 define( 'AUTH_SALT',         'oQJA.Hkl/PlS n3Hb+Q!aly$6D_i:ri$22sN%)]gRM Z/<}9n-KcJgLD{*A|5~]f' );
 define( 'SECURE_AUTH_SALT',  ':fQ(Gw-4gOuk<lso&9aQ5-]`fdXNv<rW#Qa,4ar{_]-^:XQ#InIF;X6Ttf5x%n-|' );
 define( 'LOGGED_IN_SALT',    'r;:(=-(hJmdlmF8$]G;,<ou;n$s;}al9#l/ZvC4S^HY5XY`l:L3P`c8LW1 )Nh`_' );
 define( 'NONCE_SALT',        'P@xM3@+hxUo |G5kyr8@2)r%UC$$q[:.?zZ-JC-WM0v@l&`CBmn[_i[TWQ9f~ZR&' );
 define( 'WP_CACHE_KEY_SALT', 'Q$<:Pw$[UZ<F77s>f&K-/W?ETZ *K]u2zW3c:%o1d(D(&w+&hbPi7#y|bdH3zPWR' );

/**#@-*/


/**
 * WordPress database table prefix.
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */

$table_prefix = 'wp_';

/* Add any custom values between this line and the "stop editing" line. */

/**
 * For developers: WordPress debugging mode.
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */

define( 'SAVEQUERIES', true );
define( 'WP_DEBUG', true);
define( 'WP_DEBUG_LOG', true);
define( 'FS_METHOD', 'direct' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
define( 'DOMAIN_CURRENT_SITE', 'iblog.bz' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
define( 'ELEMENTOR_MULTI_SITE', true );

// define( 'SUNRISE', 'on' );


define( 'MO_SAML_LOGGING', false );
define( 'DUPLICATOR_AUTH_KEY', 'x}<j7*POZB<#Yw-[6V6c/l+%U]`dnO(UW]Fvo%^.(9X wfk7&LnjFhXPh-IGgi)6' );
/* That's all, stop editing! Happy publishing. */

/* Multisite */
define( 'WP_ALLOW_MULTISITE', true );

// Cookie settings for different domains

//define( 'COOKIE_DOMAIN', $_SERVER['HTTP_HOST']);  for multisite this is commented out
// Let WordPress handle domain-specific cookies for multisite — prevents custom domain login redirect issues
define('COOKIE_DOMAIN', false);


if (!defined('BLOG_ID_CURRENT_SITE')) {
    define('BLOG_ID_CURRENT_SITE', 1);
}

define( 'COOKIEPATH', '/');
define( 'SITECOOKIEPATH', '/');
define( 'ADMIN_COOKIE_PATH', '/'); 
define( 'COOKIEHASH', '5ea7bc2e3014210cfbb09c59cd801a40' );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

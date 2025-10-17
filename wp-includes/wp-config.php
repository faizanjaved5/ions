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
define( 'DB_NAME', 'u150458267_dtcvmie' );

/** Database username */
define( 'DB_USER', 'u150458267_dtcvmie' );

/** Database password */
define( 'DB_PASSWORD', 'CUAdC#cc2&' );

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

define( 'AUTH_KEY',          'A^lc@HC4|)(7NO@A:2sZE&.mQePFyXQJ8 .[9vr%p9,yqCI0r7U[AD1`+t1p47pQ' );
define( 'SECURE_AUTH_KEY',   'aNl93JjhL,Q>VYc^v)Bd<_(o}(svC<,{@%C{VtCBs$oC,TX+>h~i-*yS$Bw|J{CY' );
define( 'LOGGED_IN_KEY',     'vk7RZsga^nN)ZmX]X PXGlW%./8kN>I|/):;xq_xTRNbRwM=tfE,..XKn4c]]5{~' );
define( 'NONCE_KEY',         'D?:(}PQ-mG_5`T^<S;Y;7)V78A));0^#u194Fe{`5;pv?!EDFn%,$Uy=Sp8m<$<3' );
define( 'AUTH_SALT',         '0lFD At,m /`$&RPesVy`f^-tbDqE)vL*Za oy`?%PL4Z>L_R%G{Y-Xq%{sCGWg|' );
define( 'SECURE_AUTH_SALT',  '%2^2tfZ5c![DnHfX1qG6}+l/8A%I60BNZRBLBXP5nE(&M]|r?5<gT0B=<AJ-B*oD' );
define( 'LOGGED_IN_SALT',    '}}S%lK/WTC|0k{aWoi `*?KDtUBl5$pkL}oz8_-&szvv]:DpeTqIL7haY-NQY.iE' );
define( 'NONCE_SALT',        '^ik!gKk<K(8bsIU^Rf~;yGB3F:i#oa<+M1P`;PO/5GQ^3 r]=n?H+Sye?2U=aee)' );
define( 'WP_CACHE_KEY_SALT', 'Z.%7Qk_Y0qe.@N~rQ<eK{u6OG= 97/o7VEYpK]8SY*}-&#GRn9]!eMYVN4rceIm!' );

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
define( 'COOKIEHASH', '7ab2cc3ea06b92e187804ed5496eaef8' );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

========================================================
Caveat User
========================================================

As of 6/2/2009, soo_txp_obj is alpha software. Use at your own risk.

The plugin help is minimal. For details and usage examples, see
<http://ipsedixit.net/txp/21/soo_txp_obj>


========================================================
Requirements
========================================================

soo_txp_obj is being developed on Textpattern <http://textpattern.com/> version 4.0.8, and may not have been tested on prior versions.

Requires PHP version 5 or higher. You can find your server's PHP version in the Textpattern Diagnostics tab <http://textbook.textpattern.net/wiki/index.php?title=Diagnostics>. Most web hosts support PHP 5 but it may not be your default configuration.


========================================================
Installation
========================================================

Standard Textpattern plugin installation: see
<http://textbook.textpattern.net/wiki/index.php?title=Plugins>


========================================================
Versions
========================================================

This version:

-------------------
1.0.a.6

6/2/2009

* Added *Soo_Txp_Uri* class, for URI query string manipulation
* *Soo_Html_P* and *Soo_Html_Th* can now have contents set during instantiation. Note that this will break compatibility with some previous uses, e.g. @new Soo_Html_P($atts)@.
* Added @$in@ argument to @where_in()@ method (*Soo_Txp_Data*) to allow "NOT IN" queries


===================
Previous versions:

-------------------
1.0.a.5

* Corrected SQL syntax in order_by_field() function of Soo_Txp_Data class
* Modified tag() function of Soo_Html class to handle a PHP 5.2.0 bug

-------------------
1.0.a.4

* Added count() and field() methods to the abstact Soo_Txp_Data class

-------------------
1.0.a.3

* Brought Soo_Txp_Article and Soo_Txp_Plugin up to date with Textpattern 4.0.7.

-------------------
1.0.a.2

* No significant changes, but about 35% smaller thanks to generic getters and setters using __get() and __call(). Thanks to jm for the hint.

-------------------
1.0.a.1
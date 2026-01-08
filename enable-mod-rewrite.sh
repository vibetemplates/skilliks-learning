#!/bin/bash
# Script to enable mod_rewrite in Apache

echo "=== Enabling mod_rewrite for Apache ==="
echo ""
echo "Run these commands with sudo access:"
echo ""

echo "1. Enable mod_rewrite module:"
echo "   sudo a2enmod rewrite"
echo ""

echo "2. Edit your Apache site configuration:"
echo "   sudo nano /etc/apache2/sites-available/000-default.conf"
echo ""
echo "   Or if using SSL:"
echo "   sudo nano /etc/apache2/sites-available/default-ssl.conf"
echo ""

echo "3. Add this inside the <VirtualHost> block for your site:"
cat << 'EOF'

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

EOF

echo "4. Test Apache configuration:"
echo "   sudo apache2ctl configtest"
echo ""

echo "5. Restart Apache:"
echo "   sudo systemctl restart apache2"
echo ""

echo "6. Verify mod_rewrite is enabled:"
echo "   sudo apache2ctl -M | grep rewrite"
echo ""

echo "=== Alternative: Quick commands to copy/paste ==="
echo ""
echo "sudo a2enmod rewrite"
echo "sudo systemctl restart apache2"
echo ""

echo "=== To edit the config file and add AllowOverride All: ==="
echo "sudo bash -c 'cat >> /etc/apache2/sites-available/000-default.conf << EOF

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

EOF'"
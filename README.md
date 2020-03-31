# VoucherFunding


## Requirements

| Version 	| Requirements               	|
|---------	|----------------------------	|
| 0.0.1    	| Shopware 6.1 >=	            |


# Installation

Portal & Plugin Voucher

**1. Clone git Repositories**

```bash
git clone https://github.com/shopware/portal

cd portal/custom/

mkdir plugins 

cd plugins

git clone https://github.com/shopwareDowntown/swag-voucher-funding SwagVoucherFunding
```

**2. Build Portal:**

```bash
# Back to portal directory
cd ../../

# Install dependencies
composer install

# (with option 1.dev, http://portal.test, database config..)
bin/console system:setup 

bin/console system:install --create-database --basic-setup

# If you're using valet
valet link portal.test
```

**3. Build Plugin**

```bash
bin/console plugin:refresh

bin/console plugin:install --activate --clearCache SwagVoucherFunding
```

Now you can check the plugin is successfully installed

```bash
bin/console plugin:list
```

**Run plugin migration**

```bash
# From portal directory

# Migrate
./bin/console database:migrate --all  SwagVoucherFunding

```

# Description

Wiki: https://github.com/shopwareDowntown/swag-voucher-funding/wiki Or https://github.com/shopware/portal/wiki/voucherFunding

DB Diagram: https://dbdiagram.io/d/5e7cdec74495b02c3b88d20c

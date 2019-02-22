# Billmate Checkout Payment Gateway for Magento2
By Billmate AB - [https://billmate.se](https://billmate.se/ "billmate.se")

## Documentation
2Do

## Description

Billmate Checkout is a plugin that extends Magento, allowing your customers to get their products first and pay by invoice to Billmate later (https://www.billmate.se/). This plugin utilizes Billmate Invoice, Billmate Card, Billmate Bank and Billmate Part Payment.

## Important Note
* This version is Checkout only.

## COMPATIBILITY Magento versions
2.0.x
2.1.x
2.2.x
The version 2.3.* does not support yet.

## Supported Languages
### Admin
* English
* Swedish
### Frontend
* English
* Swedish

## Installation
### Via Code Package
1. Download the latest release zip file.
2. In the root directory of your Magento installation, create the following sub-directory path:  
	```
	app/code/Billmate/
	```
3. Upload the zip files contents into the newly created directory.
4. Run these bash commands in the root Magento installation
	```bash
	php bin/magento setup:upgrade
	php bin/magento setup:static-content:deploy
	```
5. Configure the general settings under "Stores" --> "Configuration" --> "Billmate" --> "Checkout". 
6. Configure payment method specific settings under "Stores" --> "Configuration" --> "Sales" --> "Payment Methods".
7. Make a test purchase for every payment method to verify that you have made the correct settings.

## Known issues
- More Documentation will be added before version 1.0

##How to place Billmate logo on your site.
Copy the code below for the size that fits your needs.

###Large

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_l.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_l.png" alt="Billmate Payment Gateway" /></a>`

###Medium

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_m.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_m.png" alt="Billmate Payment Gateway" /></a>`

###Small

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_s.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_s.png" alt="Billmate Payment Gateway" /></a>`

## Changelog

### 0.9b (2017-09-18)
* Initial commit.
### 0.11.0b (2018-12-04)
* Made full refactoring module.
### 1.0.0 (2018-12-07)
* Added security improvements.
* Removed deprecated configurations.
* Added pre-configured setup of the module.
* Fixed design bugs.

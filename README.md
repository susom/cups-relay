# Docker Container for CUPS Printing from REDCap

This docker container is configured with drivers for a printer in the ENT offices:
 * ENT Printer1860 is a RICOH IM350F.
 * Connection can be tested with telnet to 10.253.98.245 on Port 9100

A firewall exception was added that allows printing from the current REDCap production server where this container
 will also run.

The driver for the Ricoh printer came from: https://www.openprinting.org/printer/Ricoh/Ricoh-IM_350 and may
 be black/white only at the moment.

Inside the docker network, this container listens on port 80 for inbound API calls from the REDCap EM proj_rhino.

It will display logs with a GET request containing showLogs as a querystring parameter:  e.g. http://cupsd/?showLogs

## Post Method 1: Print array of instruments as PDFs
If passed a post request containing:
 - record_id
 - event_name
 - instruments (array)
 - compact_display (boolean)

## Post Method 2: Print a single file-upload field
 - record_id
 - event_name
 - field_name
 When this is received, it will download the field (so it must be a file-upload field and must also not be empty).
 It will then print this field.
```
{
    "action": "print_file_field",
    "record_id": "123",
    "event_name": "event_1_arm_1",
    "field_name": "file_upload_field"
}
```



It will pull the PDF using the REDCap API (defined in config.ini) and print the instruments one-by-one to the configured printer.



## To set up CUPS printer VM as PDF printer locally
This is kind of complex, so be patient!


### Add to Docker Network
First thing is to run the cupsd VM in the same network as your local REDCap.
* Get the redcap_networks name from the REDCap install:
```
networks:
     redcap_network:
       name: "local_redcap_network"
```
* In cups docker-compose.yaml, set the to patch
```
networks:
  default:
    external:
      name: local_redcap_network
```

### Modify your config.ini to reference local settings, for example:
```
[redcap-settings]
;Note that the API url is your docker web container name as seen by docker
api-url = http://web/api/
api-token = 000-your-local-api-token-000
;printer-name = Ricoh3500_ENT
printer-name = Virtual_PDF_Printer
log-file-max = 500
test-mode = 0
```

### Run your cupsd printer:
`docker-compose up`

### SSH into the cupsd printer and monitor the /tmp folder
`docker exec -it cupsd /bin/bash`

### Try sending an example print command with postman or curl:
```
curl --location --request POST 'http://127.0.0.1:8081/' \
--form 'action="print_file_field"' \
--form 'record_id="11"' \
--form 'event_name="new_visit_arm_1"' \
--form 'field_name="html_summary_pdf"'
```

* Note, the output of the virtual PDF printer appears to reside at `/var/spool/cups` which you can optionally map to your
local computer for easy checking (you will have to append a .pdf suffix)


### If you want to play with the printers installed in your cupsd then you can expose 631 to the public but this isn't needed

* in cups docker-compose, set the port:
```
    ports:
      - "632:631
```
* docker network create local_redcap_network
* docker-compose up
* navigate to 127.0.0.1:632/admin
* add pdf printer
* Update connect string in EM to print
* to Add a virtual printer, go to
```
http://127.0.0.1:632/printers
```
* the default admin password: print/print
* Under the administration tab, Add Printer
* Add to printers.conf
```
http://127.0.0.1:632/printers/Virtual_PDF_Printer
```
```
http://cups-pdf:/
```







2021-03-24
I installed a second postscript printer driver to perhaps have better printer control.
- Ricoh3500_ENT is the PDF driver
- Ricoh3500_ENT_V2 is the postscript driver.

Running:
`lp -d Ricoh3500_ENT_V2 -o number-up=2 -o sides=two-sided-long-edge /pdfs/3pagetest.pdf`
successfully printed 2-up duplex in the clinic.

Going to redeploy container and switch to the V2 printer.

## MISC

### To show printer options:
`root@8b3f5d7ac16b:/etc/cups# lpoptions -p Ricoh3500_ENT_V2 -l`

### To set default options:
`lpoptions -p Ricoh3500_ENT_V2 -o Duplex=DuplexNoTumble`

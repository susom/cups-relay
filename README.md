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
 When this is receveied, it will download the field (so it must be a file-upload field and must also not be empty).
 It will then print this field.



It will pull the PDF using the REDCap API (defined in config.ini) and print the instruments one-by-one to the configured printer.

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

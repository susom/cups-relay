# Docker Container for CUPS Printing from REDCap

This docker container is configured with drivers for a printer in the ENT offices:
 * ENT Printer1860 is a RICOH IM350F.
 * Connection can be tested with telnet to 10.253.98.245 on Port 9100

A firewall exception was added that allows printing from the current REDCap production server where this container will also run.

The driver for the Ricoh printer came from: https://www.openprinting.org/printer/Ricoh/Ricoh-IM_350 and may be black/white only at the moment.

Inside the docker network, this container listens on port 80 for inbound API calls from the REDCap EM proj_rhino.

It will display logs with a GET request containing showLogs as a querystring parameter:  e.g. http://cupsd/?showLogs

If passed a post request containing:
 - record_id
 - event_name
 - instruments (array)
 - compact_display (boolean)

It will pull the PDF using the REDCap API (defined in config.ini) and print the instruments one-by-one to the configured printer.

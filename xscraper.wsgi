import sys
sys.path.insert(0, "/usr/local/lib/python3.11/site-packages")
sys.path.insert(0, "/app")

from xscraper import app as application

# Log that WSGI app is loaded
import logging
logging.basicConfig(level=logging.INFO)
logging.info("WSGI app loaded - this should only handle /api/process requests")
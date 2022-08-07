Xbox Capture Sync
=================

Simple PHP script to sync Xbox screenshots and game clips on request.

When run using Docker, the following need to be set:

* `API_KEY` environment variable, to the [OpenXBL](https://xbl.io/) API key.
* Volume mapping, so that the `/usr/src/xbox-captures-sync/Captures/` directory is available outside the container.
* Port mapping, so that virtual machine port 80 is accessible outside.

To request the latest captures are downloaded, simply access whatever IP and port the virtual machine has been mapped to.

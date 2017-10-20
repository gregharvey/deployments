from fabric.api import *
import os
import sys
import string
import ConfigParser


@task
def read_config_file(config_filename='config.ini', abort_if_missing=True, fullpath=False, remote=False):
  # Fetch the host to deploy to, from the mapfile, according to its repo and build type
  config_file = ConfigParser.RawConfigParser()
  # Force case-sensitivity
  config_file.optionxform = str
  cwd = os.getcwd()

  # Try and read a config.ini from the repo's root directory, if present
  if fullpath is False:
    path_to_config_file = cwd + '/' + config_filename
  else:
    path_to_config_file = config_filename
  if remote is False:
    print "===> Trying to read LOCAL file %s if it is present" % path_to_config_file
    if os.path.isfile(path_to_config_file):
      config_file.read(path_to_config_file)
      return config_file
    # Otherwise, abort the build / report missing file.
    else:
      if abort_if_missing is True:
        raise SystemError("===> We didn't find %s, aborting" % path_to_config_file)
      else:
        print "===> No config file found, but we will carry on regardless"
  else:
    print "===> Trying to read REMOTE file %s if it is present" % path_to_config_file
    if run("find %s -type f" % path_to_config_file).return_code == 0:
      config_file_contents = run("cat %s" % path_to_config_file)
      local_config_path = cwd + '/config.ini'
      local("echo '%s' > %s" % (config_file_contents, local_config_path))
      config_file.read(local_config_path)
      return config_file
    # Otherwise, abort the build / report missing file.
    else:
      if abort_if_missing is True:
        raise SystemError("===> We didn't find %s, aborting" % path_to_config_file)
      else:
        print "===> No config file found, but we will carry on regardless"

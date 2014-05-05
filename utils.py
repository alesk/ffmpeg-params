#!/usr/bin/env python

from copy import deepcopy


def dict_merge(target, *args):

  # Merge multiple dicts
  if len(args) > 1:
    for obj in args:
      dict_merge(target, obj)
    return target

  # Recursively merge dicts and set non-dict values
  obj = args[0]
  if not isinstance(obj, dict):
    return obj
  for k, v in obj.iteritems():
    if k in target and isinstance(target[k], dict):
      dict_merge(target[k], v)
    else:
      target[k] = deepcopy(v)
  return target


mklist = lambda x: x if isinstance(x, list) else [x]


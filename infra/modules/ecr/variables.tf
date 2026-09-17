variable "repository_names" {
  description = "One ECR repo per deployable image, e.g. [\"api\"]"
  type        = list(string)
}

variable "tags" {
  type    = map(string)
  default = {}
}

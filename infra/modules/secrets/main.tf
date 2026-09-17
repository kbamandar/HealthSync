# Declares the secret containers only. Values are set out-of-band (console/CLI)
# so they never pass through Terraform state or a tfvars file.

resource "aws_secretsmanager_secret" "this" {
  for_each = toset(var.secret_names)
  name     = "${var.name}/${each.value}"

  tags = var.tags
}

Tool to set LMX2582 registers via SPI bus on Rigol DHO800 and DHO900 series oscilloscopes. Based on reverse engineering and fully tested.

Note: Rigol misspelled LMX2582 as LXM2582, that's why most of this code uses wrong name.

It does the same job in under 0.1 second (measured with bash time command), instead of 2.35 seconds like a original Rigol tool.

It allows to change every PLL register, which allows to donwclock and overclock Rigol oscilloscopes. Or you can just use original 1250 MHz output with this tool to decrease boot time.

# Precompiled and ready to use

Look into "precompiled" folder for available options.

Example to install precompiled binary with 1800 MHz output:

```bash
adb push precompiled/spi2pll_lxm2582_1800_MHz /rigol/tools/spi2pll_lxm2582
```

# Requiments

- Android NDK (Google Android Native Development Kit) to compile. Tested with 21d+r1.
- ADB, SSH or anything else to copy this tool to the destination. Rigol DHO800 and DHO900 originally keeps it in /rigol/tools/spi2pll_lxm2582

# Compilation and installation

Example with 1250 MHz output:

```bash
php lxm2582_generate_header_and_compile.php TICSPro_registers/DHO800_DHO900_original_registers_1250_MHz.txt
adb push compiled/spi2pll_lxm2582_DHO800_DHO900_original_registers_1250_MHz /rigol/tools/spi2pll_lxm2582
```

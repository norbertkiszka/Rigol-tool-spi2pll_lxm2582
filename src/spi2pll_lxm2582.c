// Tool to set registers in LMX2582 PLL+VCO via SPI bus
// by using Rigol spi2pll_lxm2582_gpio or compatible driver.
// See: https://github.com/norbertkiszka/Linux-5.10-Rockchip/blob/develop-5.10/drivers/rigol/spi2pll_lxm2582_gpio.c
// See: https://github.com/norbertkiszka/rigol-orangerigol-linux_4.4.179/blob/main/drivers/rigol/spi2pll_lxm2582_gpio.c
//
// Copyright (C) 2025 Norbert Kiszka
// 
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2
// of the License.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// 
// See the GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

#define REGISTERS 46
#define BYTES_PER_REGISTER 3
#define BYTES REGISTERS * BYTES_PER_REGISTER

int main(void)
{
	int i;
	int offset;
	ssize_t written_bytes;
	
	do_first();
	
	if(sizeof d != BYTES)
	{
		fprintf(stderr, "Bad data...\n");
		return 1;
	}
	
	int fd = open("/dev/spi_3wires_lxm2582", O_WRONLY);
	
	if(fd == -1)
	{
		fprintf(stderr, "Can't open spi device : %s\n", strerror(errno));
		return 1;
	}
	
	for(i = 0; i < REGISTERS; i++)
	{
		offset = i * BYTES_PER_REGISTER;
		//printf("%i\n", i);
		
		written_bytes = write(fd, d+offset, BYTES_PER_REGISTER);
		
		// Norbert: Rigol driver spi2pll_lxm2582_gpio doesn't return written bytes, but rather always 0.
		//if(written_bytes < BYTES_PER_REGISTER)
		if(written_bytes < 0)
		{
			close(fd);
			//if(written_bytes > 0)
			//	offset += written_bytes;
			fprintf(stderr, "Write error at byte %i : %s\n", offset, strerror(errno));
			return 1;
		}
		
		usleep(1000);
	}
	
	close(fd);
	return 0;
}
